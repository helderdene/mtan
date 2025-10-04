# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-10-02-phase1-multi-tenant-foundation/spec.md

## Technical Requirements

### 1. Multi-Tenancy Infrastructure

**Tenant Model** (`app/Models/Tenant.php`):
- UUID primary key
- Fields: company_name, domain, subdomain, database_name, database_host, subscription_plan, max_employees, max_devices, features (JSON), is_active, trial_ends_at, subscription_starts_at, subscription_ends_at
- Methods: `getDatabaseConnection()`, `isActive()`, `canAddEmployee()`, `canAddDevice()`

**TenantResolver** (`app/Infrastructure/Multitenancy/TenantResolver.php`):
- Resolves tenant from domain, subdomain, API token, or device_id
- Caching layer for tenant resolution (1 hour TTL)
- Priority: Domain → API Token → Device ID
- Throws `TenantNotFoundException` if resolution fails

**TenantDatabaseManager** (`app/Infrastructure/Multitenancy/TenantDatabaseManager.php`):
- `createTenantDatabase(Tenant $tenant)`: Creates MySQL database with utf8mb4_unicode_ci collation
- `runTenantMigrations(Tenant $tenant)`: Runs migrations from `database/migrations/tenant/`
- `seedTenantDefaults(Tenant $tenant)`: Seeds default shifts, departments, and settings
- `createDefaultAdmin(Tenant $tenant)`: Creates admin user with credentials sent via email
- `deleteTenantDatabase(Tenant $tenant)`: Soft deletion with backup

**TenantMiddleware** (`app/Infrastructure/Multitenancy/TenantMiddleware.php`):
- Resolves tenant context before request processing
- Sets default database connection to tenant database
- Injects tenant info into view/Inertia shared data
- Handles `TenantNotFoundException` with appropriate response

**TenancyServiceProvider** (`app/Providers/TenancyServiceProvider.php`):
- Registers tenant resolver singleton
- Registers database manager
- Sets up global tenant scope for Eloquent models
- Configures cache key prefixing per tenant

### 2. Database Configuration

**Central Database Connection** (`config/database.php`):
```php
'central' => [
    'driver' => 'mysql',
    'host' => env('CENTRAL_DB_HOST', '127.0.0.1'),
    'port' => env('CENTRAL_DB_PORT', '3306'),
    'database' => env('CENTRAL_DB_DATABASE', 'attendance_central'),
    'username' => env('CENTRAL_DB_USERNAME', 'root'),
    'password' => env('CENTRAL_DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
],
```

**Dynamic Tenant Connection**:
- Tenant database connection created dynamically on tenant resolution
- Connection name: `tenant`
- Database name pattern: `tenant_{uuid_without_dashes}`
- Same credentials as central database (or tenant-specific if configured)

### 3. MQTT Integration

**MQTT Client** (`app/Infrastructure/MQTT/MQTTClient.php`):
```php
<?php

namespace App\Infrastructure\MQTT;

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MQTTClient
{
    protected MqttClient $client;
    protected array $config;
    protected array $subscribedTopics = [];

    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->initializeClient();
    }

    protected function loadConfig(): array
    {
        return DB::connection('central')
            ->table('mqtt_broker_configs')
            ->where('is_active', true)
            ->where('is_primary', true)
            ->first();
    }

    protected function initializeClient(): void
    {
        $settings = (new ConnectionSettings)
            ->setKeepAliveInterval($this->config['keep_alive'])
            ->setLastWillTopic('mqtt/system/status')
            ->setLastWillMessage(json_encode([
                'status' => 'offline',
                'timestamp' => now()->toIso8601String()
            ]))
            ->setLastWillQualityOfService(1)
            ->setUseTls($this->config['protocol'] === 'tls')
            ->setTlsSelfSignedAllowed(false);

        if ($this->config['protocol'] === 'tls') {
            $settings
                ->setTlsCertificateAuthorityFile($this->config['ca_file'])
                ->setTlsClientCertificateFile($this->config['cert_file'])
                ->setTlsClientCertificateKeyFile($this->config['key_file']);
        }

        $this->client = new MqttClient(
            $this->config['host'],
            $this->config['port'],
            $this->generateClientId(),
            MqttClient::MQTT_3_1_1
        );

        $this->client->connect($settings);

        Log::info('MQTT Client connected', [
            'broker' => $this->config['host'],
            'port' => $this->config['port']
        ]);
    }

    public function subscribe(string $topic, callable $callback, int $qos = 1): void
    {
        $this->client->subscribe(
            $topic,
            function (string $topic, string $message, bool $retained) use ($callback) {
                try {
                    $payload = json_decode($message, true);

                    if (json_last_error() !== JSON_ERROR_NONE) {
                        Log::error('Invalid JSON in MQTT message', [
                            'topic' => $topic,
                            'message' => $message,
                            'error' => json_last_error_msg()
                        ]);
                        return;
                    }

                    $callback($topic, $payload, $retained);

                } catch (\Throwable $e) {
                    Log::error('Error processing MQTT message', [
                        'topic' => $topic,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            },
            $qos
        );

        $this->subscribedTopics[$topic] = $qos;

        Log::info('Subscribed to MQTT topic', [
            'topic' => $topic,
            'qos' => $qos
        ]);
    }

    public function publish(string $topic, array $payload, int $qos = 1, bool $retain = false): void
    {
        $message = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->client->publish($topic, $message, $qos, $retain);

        Log::debug('Published MQTT message', [
            'topic' => $topic,
            'payload_size' => strlen($message),
            'qos' => $qos
        ]);
    }

    public function loop(int $allowedSleepSeconds = 1): void
    {
        while (true) {
            try {
                $this->client->loop(true, true);

                // Periodic health check
                if ($this->shouldRunHealthCheck()) {
                    $this->performHealthCheck();
                }

                sleep($allowedSleepSeconds);

            } catch (\Throwable $e) {
                Log::error('MQTT loop error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                $this->handleConnectionError($e);
            }
        }
    }

    protected function handleConnectionError(\Throwable $e): void
    {
        Log::warning('MQTT connection lost, attempting reconnection...');

        sleep(5); // Wait before reconnecting

        try {
            $this->disconnect();
            $this->initializeClient();
            $this->resubscribeTopics();

            Log::info('Successfully reconnected to MQTT broker');

        } catch (\Throwable $reconnectError) {
            Log::critical('Failed to reconnect to MQTT broker', [
                'error' => $reconnectError->getMessage()
            ]);

            // Notify administrators
            // dispatch(new SendCriticalAlert(...)); // Phase 2
        }
    }

    protected function resubscribeTopics(): void
    {
        foreach ($this->subscribedTopics as $topic => $qos) {
            $this->client->subscribe($topic, null, $qos);
        }

        Log::info('Resubscribed to all topics', [
            'topic_count' => count($this->subscribedTopics)
        ]);
    }

    protected function generateClientId(): string
    {
        return sprintf(
            'attendance_system_%s_%s',
            config('app.env'),
            Str::random(8)
        );
    }

    public function disconnect(): void
    {
        if ($this->client) {
            $this->client->disconnect();
        }
    }

    protected function shouldRunHealthCheck(): bool
    {
        $lastCheck = Cache::get('mqtt:last_health_check', 0);
        return (time() - $lastCheck) > 60; // Every 60 seconds
    }

    protected function performHealthCheck(): void
    {
        $this->publish('mqtt/system/health', [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'subscribed_topics' => array_keys($this->subscribedTopics)
        ]);

        Cache::put('mqtt:last_health_check', time(), 120);
    }
}
```

**Message Handler** (`app/Infrastructure/MQTT/MessageHandler.php`):
```php
<?php

namespace App\Infrastructure\MQTT;

use App\Jobs\ProcessAttendanceEvent;
use Illuminate\Support\Facades\Log;

class MessageHandler
{
    public function handleRecognitionEvent(string $topic, array $payload): void
    {
        $deviceId = $this->extractDeviceId($topic);

        // Record raw message for debugging
        $this->logRawMessage('recognition', $deviceId, $payload);

        // Dispatch to queue for processing
        dispatch(new ProcessAttendanceEvent($deviceId, $payload))
            ->onQueue('attendance-high-priority');
    }

    public function handleStrangerEvent(string $topic, array $payload): void
    {
        $deviceId = $this->extractDeviceId($topic);

        $this->logRawMessage('stranger', $deviceId, $payload);

        // Phase 1: Basic logging only, no stranger log storage
        Log::warning('Stranger detected', [
            'device_id' => $deviceId,
            'timestamp' => $payload['info']['time'] ?? null,
        ]);
    }

    public function handleAcknowledgement(string $topic, array $payload): void
    {
        $deviceId = $this->extractDeviceId($topic);

        $this->logRawMessage('acknowledgement', $deviceId, $payload);

        // Phase 1: Basic logging only
        // Full acknowledgement handling in Phase 3
        Log::info('Device acknowledgement received', [
            'device_id' => $deviceId,
            'operator' => $payload['operator'] ?? null,
            'result' => $payload['result'] ?? null,
        ]);
    }

    public function handleDeviceStatus(string $topic, array $payload): void
    {
        $deviceId = $this->extractDeviceId($topic);

        // Update device last seen timestamp
        $this->updateDeviceHeartbeat($deviceId);
    }

    protected function extractDeviceId(string $topic): string
    {
        // Topic format: mqtt/face/{device_id}/Rec
        preg_match('/mqtt\\/face\\/([^\\/]+)\\//', $topic, $matches);

        return $matches[1] ?? throw new \InvalidArgumentException('Invalid topic format');
    }

    protected function logRawMessage(string $type, string $deviceId, array $payload): void
    {
        if (config('app.debug') || config('mqtt.log_raw_messages')) {
            Log::channel('mqtt')->debug('MQTT message received', [
                'type' => $type,
                'device_id' => $deviceId,
                'payload' => $payload
            ]);
        }
    }

    protected function updateDeviceHeartbeat(string $deviceId): void
    {
        Cache::put(
            "device:heartbeat:{$deviceId}",
            now()->toDateTimeString(),
            now()->addMinutes(5)
        );
    }
}
```

**MQTT Consumer Command** (`app/Console/Commands/MQTTConsumerCommand.php`):
```php
<?php

namespace App\Console\Commands;

use App\Infrastructure\MQTT\MQTTClient;
use App\Infrastructure\MQTT\MessageHandler;
use Illuminate\Console\Command;

class MQTTConsumerCommand extends Command
{
    protected $signature = 'mqtt:consume';
    protected $description = 'Start MQTT consumer for attendance events';

    public function handle(MQTTClient $client, MessageHandler $handler): int
    {
        $this->info('Starting MQTT consumer...');

        // Subscribe to recognition events
        $client->subscribe('mqtt/face/+/Rec', function($topic, $payload) use ($handler) {
            $handler->handleRecognitionEvent($topic, $payload);
        }, 2); // QoS 2 for critical attendance messages

        // Subscribe to stranger events
        $client->subscribe('mqtt/face/+/Stranger', function($topic, $payload) use ($handler) {
            $handler->handleStrangerEvent($topic, $payload);
        }, 1);

        // Subscribe to acknowledgements
        $client->subscribe('mqtt/face/+/Ack', function($topic, $payload) use ($handler) {
            $handler->handleAcknowledgement($topic, $payload);
        }, 1);

        // Subscribe to device status
        $client->subscribe('mqtt/face/+/Status', function($topic, $payload) use ($handler) {
            $handler->handleDeviceStatus($topic, $payload);
        }, 0);

        $this->info('MQTT consumer started successfully');
        $this->info('Subscribed to topics: mqtt/face/+/Rec, mqtt/face/+/Stranger, mqtt/face/+/Ack, mqtt/face/+/Status');

        // Start loop
        $client->loop();

        return 0;
    }
}
```

### 4. Employee Management

**Employee Model** (`app/Domain/Employee/Models/Employee.php`):
- Fields: employee_code (unique), custom_id (unique, auto-generated), name, email, phone, department_id, designation, employee_type, card_number, joining_date, leaving_date, reporting_manager_id, is_active, metadata (JSON)
- Relationships: `belongsTo(Department)`, `belongsTo(Employee as reportingManager)`, `hasMany(EmployeeShift)`, `hasMany(AttendanceRecord)`, `hasMany(DeviceEnrollment)`
- Scopes: `active()`, `byDepartment($departmentId)`
- Methods: `generateCustomId()` (returns next EMP### in sequence), `isEnrolledOnDevice($deviceId)`

**Employee Controller** (`app/Http/Controllers/EmployeeController.php`):
- `index()`: List employees with pagination, filters (department, status)
- `create()`: Show employee creation form
- `store(EmployeeRequest $request)`: Create employee, generate custom_id, dispatch device sync job
- `edit(Employee $employee)`: Show edit form
- `update(EmployeeRequest $request, Employee $employee)`: Update employee, dispatch device sync if name changed
- `destroy(Employee $employee)`: Soft delete employee, dispatch device removal job

**Employee Request Validation** (`app/Http/Requests/EmployeeRequest.php`):
- Validates: employee_code (unique per tenant), name (required, max 255), email (nullable, unique, email format), department_id (exists in departments), employee_type (enum: full-time, part-time, contractor, intern)

**Device Sync Job** (`app/Jobs/SyncEmployeeToDevices.php`):
- Queued on `attendance-default` queue
- For each active device in tenant, publishes MQTT AddPerson command
- Tracks sync status in `device_enrollments` table

### 5. Shift Management

**Shift Model** (`app/Domain/Shift/Models/Shift.php`):
- Fields: name, code (unique), start_time, end_time, break_start, break_end, grace_period_minutes (default 15), early_departure_threshold_minutes, overtime_threshold_minutes, half_day_threshold_minutes, working_days (JSON array), shift_type (enum: fixed, flexible, rotating - only fixed in Phase 1), is_overnight, color_code, is_active, description, metadata (JSON)
- Relationships: `hasMany(EmployeeShift)`
- Scopes: `active()`, `fixed()`
- Methods: `isWorkingDay(Carbon $date)`, `getShiftDuration()`

**Shift Controller** (`app/Http/Controllers/ShiftController.php`):
- `index()`: List shifts
- `create()`: Show shift creation form
- `store(ShiftRequest $request)`: Create shift
- `edit(Shift $shift)`: Show edit form
- `update(ShiftRequest $request, Shift $shift)`: Update shift
- `destroy(Shift $shift)`: Soft delete shift

**Employee Shift Assignment** (`app/Domain/Shift/Models/EmployeeShift.php`):
- Pivot table with fields: employee_id, shift_id, effective_from, effective_to (nullable), assigned_by, assignment_reason, is_active
- Only one active shift per employee in Phase 1
- Business rule: Cannot assign overlapping shifts to same employee

### 6. Device Management

**Device Registry (Central Database)** (`app/Models/DeviceRegistry.php`):
- Central database model (uses `central` connection)
- Fields: tenant_id, device_id (unique), device_name, device_type, location, ip_address, mac_address, firmware_version, is_active, last_seen_at, registered_at, metadata (JSON)
- Relationships: `belongsTo(Tenant)`
- Methods: `updateHeartbeat()`, `isOnline()` (last_seen within 5 minutes)

**Device Model (Tenant Database)** (`app/Domain/Device/Models/Device.php`):
- Tenant database model
- Fields: device_id (matches central registry), name, location, device_type, ip_address, mac_address, firmware_version, capacity, current_count, is_entry_device, is_exit_device, timezone, settings (JSON), is_active, last_sync_at, last_heartbeat_at
- Relationships: `hasMany(AttendanceRecord)`, `hasMany(DeviceEnrollment)`
- Methods: `canAccommodateEmployee()` (checks capacity)

**Device Enrollment** (`app/Models/DeviceEnrollment.php`):
- Tracks employee sync status per device
- Fields: employee_id, device_id, sync_requested_at, enrolled_at, is_enrolled, enrollment_quality, sync_status (enum: pending, synced, enrolled, failed), sync_attempts, last_sync_error, metadata (JSON)
- Relationships: `belongsTo(Employee)`, `belongsTo(Device)`

### 7. Attendance Record Processing (Phase 1 - Simplified)

**Phase 1 Scope**: Basic attendance record creation from MQTT messages with direction defaulted to 'check-in'. Smart direction detection, violation checking, and daily summaries are deferred to Phase 2.

**Attendance Record Model** (`app/Domain/Attendance/Models/AttendanceRecord.php`):
- Fields: employee_id, device_id, record_id (unique from device), timestamp, direction (default: 'check-in'), recognition_score, temperature, mask_detected, photo_path (nullable, Phase 1 doesn't store), processing_status, shift_id, is_late (always false in Phase 1), is_early_departure (always false in Phase 1), late_minutes (always 0 in Phase 1), early_departure_minutes (always 0 in Phase 1), remarks, metadata (JSON)
- Relationships: `belongsTo(Employee)`, `belongsTo(Device)`, `belongsTo(Shift)`
- Scopes: `forEmployee($employeeId)`, `forDate(Carbon $date)`, `recent()`

**Attendance Event DTO** (`app/Domain/Attendance/DTOs/AttendanceEventDTO.php`):
```php
<?php

namespace App\Domain\Attendance\DTOs;

use Carbon\Carbon;

class AttendanceEventDTO
{
    public function __construct(
        public string $customId,          // Employee custom_id from MQTT message
        public int $deviceId,             // Tenant device database ID
        public string $recordId,          // Unique record ID from device
        public Carbon $timestamp,         // Event timestamp from device
        public float $recognitionScore,   // Face match confidence 0-100 from device
        public ?float $temperature = null,
        public ?bool $maskDetected = null,
        public ?string $photo = null,     // Base64 photo (Phase 1: received but not stored)
        public ?array $metadata = null
    ) {}

    public static function fromMqttPayload(array $payload, int $deviceId): self
    {
        $info = $payload['info'];

        return new self(
            customId: $info['custom_id'],        // Primary identifier for employee lookup
            deviceId: $deviceId,
            recordId: $info['RecordID'],
            timestamp: Carbon::parse($info['time']),
            recognitionScore: (float) $info['similarity1'],
            temperature: isset($info['temperature']) ? (float) $info['temperature'] : null,
            maskDetected: isset($info['mask_status']) ? (bool) $info['mask_status'] : null,
            photo: $info['pic'] ?? null,
            metadata: [
                'person_id' => $info['personId'],      // Device internal ID (not used for lookup)
                'person_name' => $info['personName'],
                'device_name' => $info['facesluiceName'],
                'verify_status' => $info['VerifyStatus'],
            ]
        );
    }
}
```

**Process Attendance Event Job** (`app/Jobs/ProcessAttendanceEvent.php`):
```php
<?php

namespace App\Jobs;

use App\Domain\Attendance\DTOs\AttendanceEventDTO;
use App\Domain\Attendance\Models\AttendanceRecord;
use App\Domain\Employee\Models\Employee;
use App\Infrastructure\Multitenancy\TenantResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAttendanceEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [5, 10, 20]; // Exponential backoff in seconds

    public function __construct(
        public string $deviceId,
        public array $mqttPayload
    ) {}

    public function handle(TenantResolver $tenantResolver): void
    {
        // 1. Resolve tenant from device_id
        $tenant = $tenantResolver->resolveByDevice($this->deviceId);

        if (!$tenant) {
            Log::error('Tenant not found for device', ['device_id' => $this->deviceId]);
            $this->fail(new \Exception('Tenant not found'));
            return;
        }

        // 2. Initialize tenant context
        tenancy()->initialize($tenant);

        try {
            // 3. Find tenant device by device_id
            $device = \App\Domain\Device\Models\Device::where('device_id', $this->deviceId)->first();

            if (!$device) {
                throw new \Exception("Device not found in tenant database: {$this->deviceId}");
            }

            // 4. Parse MQTT payload into DTO
            $event = AttendanceEventDTO::fromMqttPayload($this->mqttPayload, $device->id);

            // 5. Check for duplicate record
            if ($this->isDuplicate($event)) {
                Log::info('Duplicate attendance record ignored', [
                    'custom_id' => $event->customId,
                    'timestamp' => $event->timestamp,
                    'device_id' => $this->deviceId,
                ]);
                return;
            }

            // 6. Find employee by custom_id
            $employee = Employee::where('custom_id', $event->customId)
                ->where('is_active', true)
                ->first();

            if (!$employee) {
                Log::warning('Employee not found for custom_id', [
                    'custom_id' => $event->customId,
                    'device_id' => $this->deviceId,
                    'timestamp' => $event->timestamp,
                ]);
                throw new \Exception("Employee not found: {$event->customId}");
            }

            // 7. Get employee's current shift
            $shift = $employee->shifts()
                ->wherePivot('is_active', true)
                ->wherePivot('effective_from', '<=', $event->timestamp->toDateString())
                ->where(function($query) use ($event) {
                    $query->wherePivot('effective_to', '>=', $event->timestamp->toDateString())
                          ->orWherePivot('effective_to', null);
                })
                ->first();

            // 8. Create attendance record (Phase 1: direction always 'check-in')
            $record = AttendanceRecord::create([
                'employee_id' => $employee->id,
                'device_id' => $device->id,
                'record_id' => $event->recordId,
                'timestamp' => $event->timestamp,
                'direction' => 'check-in',  // Phase 1: Default direction
                'recognition_score' => $event->recognitionScore,
                'temperature' => $event->temperature,
                'mask_detected' => $event->maskDetected,
                'photo_path' => null,  // Phase 1: Photos not stored
                'processing_status' => 'processed',
                'shift_id' => $shift?->id,
                'is_late' => false,    // Phase 1: No violation detection
                'is_early_departure' => false,
                'late_minutes' => 0,
                'early_departure_minutes' => 0,
                'metadata' => $event->metadata,
            ]);

            Log::info('Attendance record created', [
                'record_id' => $record->id,
                'employee_id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'custom_id' => $event->customId,
                'timestamp' => $event->timestamp,
                'device_id' => $this->deviceId,
            ]);

            // 9. Broadcast real-time update (if Laravel Echo configured)
            // broadcast(new AttendanceRecorded($record)); // Phase 3

        } finally {
            tenancy()->end();
        }
    }

    protected function isDuplicate(AttendanceEventDTO $event): bool
    {
        $duplicateWindow = 5; // minutes

        // Find employee first
        $employee = Employee::where('custom_id', $event->customId)->first();

        if (!$employee) {
            return false; // Will be handled as not found in main process
        }

        return AttendanceRecord::where('employee_id', $employee->id)
            ->where('timestamp', '>=', $event->timestamp->copy()->subMinutes($duplicateWindow))
            ->where('timestamp', '<=', $event->timestamp->copy()->addMinutes($duplicateWindow))
            ->exists();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessAttendanceEvent job failed', [
            'device_id' => $this->deviceId,
            'payload' => $this->mqttPayload,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
```

**Key Phase 1 Simplifications**:
- Direction is hardcoded to 'check-in'
- No violation detection (is_late, is_early_departure always false)
- Photos received but not stored (photo_path always null)
- No daily summary updates (deferred to Phase 2)
- No real-time broadcasting (deferred to Phase 3)

### 8. Queue Configuration

**Queue Connections** (`config/queue.php`):
```php
'attendance-high-priority' => [
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => 'attendance-high-priority',
    'retry_after' => 60,
    'block_for' => 5,
],
'attendance-default' => [
    'driver' => 'redis',
    'connection' => 'default',
    'queue' => 'attendance-default',
    'retry_after' => 90,
],
```

**Supervisor Configuration** (`/etc/supervisor/conf.d/attendance-queues.conf`):
```ini
[program:attendance-high]
command=php /path/to/artisan queue:work redis --queue=attendance-high-priority --tries=3 --timeout=60
numprocs=3

[program:attendance-default]
command=php /path/to/artisan queue:work redis --queue=attendance-default --tries=3 --timeout=90
numprocs=2

[program:mqtt-consumer]
command=php /path/to/artisan mqtt:consume
numprocs=1
autorestart=true
```

### 9. Authentication & Authorization

**Authentication**:
- Laravel Fortify with Inertia.js views (already configured)
- Login, registration, password reset, email verification
- Two-factor authentication support (already configured)
- Tenant-scoped authentication: Users belong to specific tenant

**Authorization**:
- Basic role system: `super_admin` (central database), `tenant_admin`, `hr_manager`, `facility_manager` (tenant database)
- Gates defined in `AuthServiceProvider`:
  - `manage-tenants`: Only super_admins
  - `manage-employees`: tenant_admin, hr_manager
  - `view-attendance`: tenant_admin, hr_manager, facility_manager
  - `manage-shifts`: tenant_admin, hr_manager
  - `manage-devices`: tenant_admin

### 10. Console Commands

**Tenant Management**:
- `php artisan tenant:create --name="Company" --domain="company.com" --subdomain="company"`: Create new tenant
- `php artisan tenant:provision {tenant_id}`: Provision tenant database
- `php artisan tenant:migrate {tenant_id}`: Run tenant migrations
- `php artisan tenant:seed {tenant_id}`: Seed tenant defaults
- `php artisan tenant:list`: List all tenants

**MQTT**:
- `php artisan mqtt:consume`: Start MQTT consumer (long-running)
- `php artisan mqtt:test-connection`: Test MQTT broker connection
- `php artisan mqtt:publish-test --device=device001 --custom-id=EMP001`: Publish test attendance message

**Device Sync**:
- `php artisan device:sync-employee {employee_id}`: Sync employee to all devices
- `php artisan device:sync-all`: Sync all employees to all devices

### 11. Frontend Pages (Inertia.js)

**Employee Management**:
- `resources/js/pages/employees/Index.vue`: Employee list with filters
- `resources/js/pages/employees/Create.vue`: Create employee form
- `resources/js/pages/employees/Edit.vue`: Edit employee form
- `resources/js/pages/employees/Show.vue`: Employee details with device enrollment status

**Shift Management**:
- `resources/js/pages/shifts/Index.vue`: Shift list
- `resources/js/pages/shifts/Create.vue`: Create shift form
- `resources/js/pages/shifts/Edit.vue`: Edit shift form

**Device Management**:
- `resources/js/pages/devices/Index.vue`: Device list with status
- `resources/js/pages/devices/Register.vue`: Register new device

**Attendance (Basic)**:
- `resources/js/pages/attendance/Index.vue`: Recent attendance records list (no analysis yet)

## External Dependencies

**PHP Packages** (to be added to composer.json):
- **php-mqtt/client** (^1.7): MQTT client library for PHP
  - **Justification**: Required for MQTT protocol communication with biometric devices. Supports MQTT 5.0, TLS, QoS levels, and auto-reconnection.

- **ramsey/uuid** (^4.7): UUID generation
  - **Justification**: Used for generating tenant UUIDs as primary keys for better distribution and security.

**No new JavaScript dependencies** required - all needs met by existing stack (Vue 3, TypeScript, Inertia.js, Reka UI, Tailwind CSS).

## Performance Considerations

- **Database Indexing**: Index on employees.custom_id, attendance_records.timestamp, attendance_records.employee_id
- **Query Optimization**: Eager load relationships to avoid N+1 queries
- **Caching**: Cache tenant resolution, shift assignments, device lists (1 hour TTL)
- **Queue Workers**: Run 3 workers for high-priority queue, 2 for default queue
- **MQTT Consumer**: Single consumer process, scales with Supervisor configuration
- **Database Connection Pooling**: Configure MySQL max_connections based on expected tenant count

## Security Considerations

- **Tenant Isolation**: Enforce at middleware level, test thoroughly
- **MQTT TLS**: Require TLS 1.2+ for all MQTT connections
- **Input Validation**: Validate all user inputs, sanitize before database storage
- **SQL Injection Prevention**: Use Eloquent ORM parameter binding
- **Rate Limiting**: Apply to API endpoints (5 requests per minute for device registration)
- **CSRF Protection**: Enabled by default in Laravel
- **Password Hashing**: Use bcrypt (Laravel default)
- **Device Authentication**: MQTT broker uses certificate-based authentication per device
