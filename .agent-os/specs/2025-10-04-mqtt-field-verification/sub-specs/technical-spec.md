# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-10-04-mqtt-field-verification/spec.md

> Created: 2025-10-04
> Version: 1.0.0

## Technical Requirements

### 1. Expected MQTT Payload Structure

Per `CLAUDE.md`, the standard MQTT recognition event payload contains:

```json
{
  "info": {
    "custom_id": "EMP001",
    "RecordID": "REC123456",
    "time": "2025-10-02 09:05:23",
    "similarity1": 98.5,
    "personId": "12345",
    "personName": "John Doe",
    "facesluiceName": "Main Entrance",
    "VerifyStatus": "1",
    "temperature": 36.5,
    "mask_status": 1,
    "pic": "base64_encoded_image"
  }
}
```

**Field Definitions:**

| Field Name | Type | Required | Description | Example Values |
|------------|------|----------|-------------|----------------|
| `custom_id` | string | **Yes** | System-generated employee ID (primary identifier) | `"EMP001"`, `"EMP123"` |
| `RecordID` | string | **Yes** | Unique device-generated record ID | `"REC123456"` |
| `time` | string (datetime) | **Yes** | Recognition timestamp in device timezone | `"2025-10-02 09:05:23"` |
| `similarity1` | float | **Yes** | Biometric match confidence score (0-100) | `98.5`, `95.2` |
| `personId` | string | No | Device-internal person ID (DO NOT use for lookup) | `"12345"` |
| `personName` | string | No | Employee name as stored on device | `"John Doe"` |
| `facesluiceName` | string | No | Device location/name | `"Main Entrance"` |
| `VerifyStatus` | string | No | Device verification result (`"1"` = success) | `"1"`, `"0"` |
| `temperature` | float | No | Body temperature reading (if device has sensor) | `36.5`, `37.2` |
| `mask_status` | int | No | Mask detection status (`1` = wearing, `0` = not wearing) | `1`, `0` |
| `pic` | string | No | Base64-encoded image of recognition event | `"data:image/jpeg;base64,..."` |

**CRITICAL NOTES:**
- `custom_id` is the ONLY field that should be used for employee lookup
- `personId` is device-internal and may not match system employee IDs
- `time` format may vary by device manufacturer - must handle multiple formats
- Optional fields may be absent or null depending on device model/configuration

### 2. Current AttendanceEventDTO Review

**Location:** `app/Domain/Attendance/DTOs/AttendanceEventDTO.php` (to be implemented/verified)

**Required Fields to Verify:**

```php
namespace App\Domain\Attendance\DTOs;

class AttendanceEventDTO
{
    public function __construct(
        // Required fields (must be present in all messages)
        public readonly string $customId,         // Maps to: custom_id
        public readonly string $recordId,         // Maps to: RecordID
        public readonly \DateTimeImmutable $timestamp, // Maps to: time (parsed)
        public readonly float $similarityScore,   // Maps to: similarity1

        // Device context (required for tenant resolution)
        public readonly string $deviceId,         // From MQTT topic

        // Optional fields (may be null)
        public readonly ?string $personId = null,        // Maps to: personId
        public readonly ?string $personName = null,      // Maps to: personName
        public readonly ?string $deviceName = null,      // Maps to: facesluiceName
        public readonly ?string $verifyStatus = null,    // Maps to: VerifyStatus
        public readonly ?float $temperature = null,      // Maps to: temperature
        public readonly ?int $maskStatus = null,         // Maps to: mask_status
        public readonly ?string $photo = null,           // Maps to: pic
    ) {
        // Validation logic
        $this->validateRequiredFields();
        $this->validateDataTypes();
    }

    private function validateRequiredFields(): void
    {
        if (empty($this->customId)) {
            throw new \InvalidArgumentException('custom_id is required');
        }

        if (empty($this->recordId)) {
            throw new \InvalidArgumentException('RecordID is required');
        }

        if ($this->similarityScore < 0 || $this->similarityScore > 100) {
            throw new \InvalidArgumentException('similarity1 must be between 0 and 100');
        }
    }

    private function validateDataTypes(): void
    {
        // Validate optional fields if present
        if ($this->temperature !== null && ($this->temperature < 30 || $this->temperature > 45)) {
            throw new \InvalidArgumentException('temperature out of valid range (30-45°C)');
        }

        if ($this->maskStatus !== null && !in_array($this->maskStatus, [0, 1], true)) {
            throw new \InvalidArgumentException('mask_status must be 0 or 1');
        }
    }

    public function toArray(): array
    {
        return [
            'custom_id' => $this->customId,
            'record_id' => $this->recordId,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'similarity_score' => $this->similarityScore,
            'device_id' => $this->deviceId,
            'person_id' => $this->personId,
            'person_name' => $this->personName,
            'device_name' => $this->deviceName,
            'verify_status' => $this->verifyStatus,
            'temperature' => $this->temperature,
            'mask_status' => $this->maskStatus,
            'photo' => $this->photo,
        ];
    }
}
```

### 3. MessageHandler Field Extraction Verification

**Location:** `app/Infrastructure/MQTT/MessageHandler.php` (to be implemented/verified)

**Expected Implementation:**

```php
namespace App\Infrastructure\MQTT;

use App\Domain\Attendance\DTOs\AttendanceEventDTO;
use Illuminate\Support\Facades\Log;

class MessageHandler
{
    public function handleRecognitionEvent(string $topic, array $payload): void
    {
        try {
            // Extract device_id from topic: mqtt/face/{device_id}/Rec
            $deviceId = $this->extractDeviceId($topic);

            // Validate payload structure
            if (!isset($payload['info'])) {
                Log::error('MQTT payload missing "info" key', [
                    'topic' => $topic,
                    'payload' => $payload,
                ]);
                return;
            }

            $info = $payload['info'];

            // Create DTO with field extraction
            $dto = $this->createAttendanceEventDTO($deviceId, $info);

            // Log successful extraction
            Log::info('MQTT recognition event received', [
                'device_id' => $deviceId,
                'custom_id' => $dto->customId,
                'record_id' => $dto->recordId,
                'similarity_score' => $dto->similarityScore,
                'has_temperature' => $dto->temperature !== null,
                'has_photo' => $dto->photo !== null,
            ]);

            // Dispatch to queue
            dispatch(new ProcessAttendanceEvent($dto));

        } catch (\Exception $e) {
            Log::error('Failed to process MQTT recognition event', [
                'topic' => $topic,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function createAttendanceEventDTO(string $deviceId, array $info): AttendanceEventDTO
    {
        // Required field extraction with validation
        $customId = $this->extractRequiredField($info, 'custom_id', 'string');
        $recordId = $this->extractRequiredField($info, 'RecordID', 'string');
        $timestamp = $this->parseTimestamp($info['time'] ?? null);
        $similarityScore = $this->extractRequiredField($info, 'similarity1', 'float');

        // Optional field extraction
        $personId = $this->extractOptionalField($info, 'personId', 'string');
        $personName = $this->extractOptionalField($info, 'personName', 'string');
        $deviceName = $this->extractOptionalField($info, 'facesluiceName', 'string');
        $verifyStatus = $this->extractOptionalField($info, 'VerifyStatus', 'string');
        $temperature = $this->extractOptionalField($info, 'temperature', 'float');
        $maskStatus = $this->extractOptionalField($info, 'mask_status', 'int');
        $photo = $this->extractOptionalField($info, 'pic', 'string');

        return new AttendanceEventDTO(
            customId: $customId,
            recordId: $recordId,
            timestamp: $timestamp,
            similarityScore: $similarityScore,
            deviceId: $deviceId,
            personId: $personId,
            personName: $personName,
            deviceName: $deviceName,
            verifyStatus: $verifyStatus,
            temperature: $temperature,
            maskStatus: $maskStatus,
            photo: $photo,
        );
    }

    private function extractRequiredField(array $data, string $key, string $type): mixed
    {
        if (!array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            throw new \InvalidArgumentException("Required field '{$key}' is missing or empty");
        }

        $value = $data[$key];

        // Type casting with validation
        return match ($type) {
            'string' => (string) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            default => $value,
        };
    }

    private function extractOptionalField(array $data, string $key, string $type): mixed
    {
        if (!array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            Log::debug("Optional field '{$key}' not present in MQTT payload", [
                'available_keys' => array_keys($data),
            ]);
            return null;
        }

        $value = $data[$key];

        // Type casting with validation
        return match ($type) {
            'string' => (string) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            default => $value,
        };
    }

    private function parseTimestamp(?string $time): \DateTimeImmutable
    {
        if (empty($time)) {
            throw new \InvalidArgumentException('Timestamp field "time" is required');
        }

        // Try multiple date formats (device manufacturers vary)
        $formats = [
            'Y-m-d H:i:s',
            'Y/m/d H:i:s',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i:sP',
        ];

        foreach ($formats as $format) {
            try {
                $timestamp = \DateTimeImmutable::createFromFormat($format, $time);
                if ($timestamp !== false) {
                    return $timestamp;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Fallback to strtotime
        try {
            return new \DateTimeImmutable($time);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException("Unable to parse timestamp: {$time}");
        }
    }

    private function extractDeviceId(string $topic): string
    {
        // Extract from topic pattern: mqtt/face/{device_id}/Rec
        preg_match('/mqtt\/face\/([^\/]+)\/Rec/', $topic, $matches);

        if (!isset($matches[1])) {
            throw new \InvalidArgumentException("Unable to extract device_id from topic: {$topic}");
        }

        return $matches[1];
    }
}
```

### 4. ProcessAttendanceEvent Job Field Usage

**Location:** `app/Jobs/ProcessAttendanceEvent.php` (to be implemented/verified)

**Expected Field Usage:**

```php
namespace App\Jobs;

use App\Domain\Attendance\DTOs\AttendanceEventDTO;
use App\Models\Employee;
use App\Models\AttendanceRecord;
use Illuminate\Support\Facades\Log;

class ProcessAttendanceEvent implements ShouldQueue
{
    public function __construct(
        private readonly AttendanceEventDTO $event
    ) {}

    public function handle(): void
    {
        // 1. Find employee by custom_id (required field)
        $employee = Employee::where('custom_id', $this->event->customId)->first();

        if (!$employee) {
            Log::warning('Employee not found for recognition event', [
                'custom_id' => $this->event->customId,
                'device_id' => $this->event->deviceId,
                'record_id' => $this->event->recordId,
            ]);

            // Log as stranger event if similarity score is high
            if ($this->event->similarityScore >= 70) {
                $this->logStrangerEvent();
            }

            return;
        }

        // 2. Create attendance record with ALL fields
        $attendanceRecord = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'device_id' => $this->event->deviceId,
            'recognition_time' => $this->event->timestamp,
            'similarity_score' => $this->event->similarityScore,
            'record_id' => $this->event->recordId,

            // Optional fields (nullable in database)
            'device_name' => $this->event->deviceName,
            'verify_status' => $this->event->verifyStatus,
            'temperature' => $this->event->temperature,
            'mask_status' => $this->event->maskStatus,
            'photo_path' => $this->event->photo ? $this->storePhoto() : null,

            // Direction detection (using smart algorithm)
            'direction' => $this->detectDirection($employee),
        ]);

        // 3. Log metadata fields for debugging
        Log::info('Attendance record created', [
            'employee_id' => $employee->id,
            'custom_id' => $this->event->customId,
            'person_name' => $this->event->personName, // Log but don't use for lookup
            'device_person_id' => $this->event->personId, // Log but don't use for lookup
            'temperature' => $this->event->temperature,
            'mask_status' => $this->event->maskStatus,
            'has_photo' => $this->event->photo !== null,
        ]);

        // 4. Continue with attendance processing...
        $this->updateDailySummary($attendanceRecord);
        $this->checkViolations($attendanceRecord);
    }

    private function storePhoto(): ?string
    {
        // Store base64 photo to storage and return path
        // Implementation depends on storage strategy
        return null; // Placeholder
    }
}
```

**Field Usage Summary:**

| Field | Used For | Required |
|-------|----------|----------|
| `custom_id` | Employee lookup (PRIMARY KEY) | Yes |
| `record_id` | Deduplication, device sync tracking | Yes |
| `timestamp` | Recognition time, direction detection | Yes |
| `similarity_score` | Confidence validation, stranger detection | Yes |
| `device_id` | Tenant resolution, device tracking | Yes |
| `person_id` | Logging/debugging only (NOT for lookup) | No |
| `person_name` | Logging/debugging only | No |
| `device_name` | Attendance record metadata | No |
| `verify_status` | Device verification result logging | No |
| `temperature` | Health screening record | No |
| `mask_status` | Compliance tracking | No |
| `photo` | Evidence storage for disputes | No |

### 5. Test Coverage Requirements

#### Unit Tests - AttendanceEventDTO

**File:** `tests/Unit/AttendanceEventDTOTest.php`

```php
test('creates DTO with all required fields', function () {
    $dto = new AttendanceEventDTO(
        customId: 'EMP001',
        recordId: 'REC123',
        timestamp: new \DateTimeImmutable('2025-10-02 09:05:00'),
        similarityScore: 98.5,
        deviceId: 'DEV001',
    );

    expect($dto->customId)->toBe('EMP001');
    expect($dto->recordId)->toBe('REC123');
    expect($dto->similarityScore)->toBe(98.5);
    expect($dto->deviceId)->toBe('DEV001');
});

test('creates DTO with all optional fields', function () {
    $dto = new AttendanceEventDTO(
        customId: 'EMP001',
        recordId: 'REC123',
        timestamp: new \DateTimeImmutable(),
        similarityScore: 98.5,
        deviceId: 'DEV001',
        personId: '12345',
        personName: 'John Doe',
        deviceName: 'Main Entrance',
        verifyStatus: '1',
        temperature: 36.5,
        maskStatus: 1,
        photo: 'base64_string',
    );

    expect($dto->personId)->toBe('12345');
    expect($dto->personName)->toBe('John Doe');
    expect($dto->deviceName)->toBe('Main Entrance');
    expect($dto->temperature)->toBe(36.5);
    expect($dto->maskStatus)->toBe(1);
    expect($dto->photo)->toBe('base64_string');
});

test('throws exception for missing custom_id', function () {
    new AttendanceEventDTO(
        customId: '',
        recordId: 'REC123',
        timestamp: new \DateTimeImmutable(),
        similarityScore: 98.5,
        deviceId: 'DEV001',
    );
})->throws(\InvalidArgumentException::class, 'custom_id is required');

test('throws exception for invalid similarity score', function () {
    new AttendanceEventDTO(
        customId: 'EMP001',
        recordId: 'REC123',
        timestamp: new \DateTimeImmutable(),
        similarityScore: 150.0, // Invalid: > 100
        deviceId: 'DEV001',
    );
})->throws(\InvalidArgumentException::class, 'similarity1 must be between 0 and 100');

test('throws exception for invalid temperature', function () {
    new AttendanceEventDTO(
        customId: 'EMP001',
        recordId: 'REC123',
        timestamp: new \DateTimeImmutable(),
        similarityScore: 98.5,
        deviceId: 'DEV001',
        temperature: 50.0, // Invalid: too high
    );
})->throws(\InvalidArgumentException::class, 'temperature out of valid range');

test('toArray method includes all fields', function () {
    $dto = new AttendanceEventDTO(
        customId: 'EMP001',
        recordId: 'REC123',
        timestamp: new \DateTimeImmutable('2025-10-02 09:05:00'),
        similarityScore: 98.5,
        deviceId: 'DEV001',
        temperature: 36.5,
        maskStatus: 1,
    );

    $array = $dto->toArray();

    expect($array)->toHaveKeys([
        'custom_id', 'record_id', 'timestamp', 'similarity_score',
        'device_id', 'temperature', 'mask_status'
    ]);
});
```

#### Unit Tests - MessageHandler

**File:** `tests/Unit/MessageHandlerTest.php`

```php
test('extracts all fields from valid MQTT payload', function () {
    $payload = [
        'info' => [
            'custom_id' => 'EMP001',
            'RecordID' => 'REC123',
            'time' => '2025-10-02 09:05:23',
            'similarity1' => 98.5,
            'personId' => '12345',
            'personName' => 'John Doe',
            'facesluiceName' => 'Main Entrance',
            'VerifyStatus' => '1',
            'temperature' => 36.5,
            'mask_status' => 1,
            'pic' => 'base64_encoded_image',
        ]
    ];

    Queue::fake();

    $handler = new MessageHandler();
    $handler->handleRecognitionEvent('mqtt/face/DEV001/Rec', $payload);

    Queue::assertPushed(ProcessAttendanceEvent::class, function ($job) {
        $dto = $job->event;

        return $dto->customId === 'EMP001'
            && $dto->recordId === 'REC123'
            && $dto->similarityScore === 98.5
            && $dto->personId === '12345'
            && $dto->personName === 'John Doe'
            && $dto->deviceName === 'Main Entrance'
            && $dto->temperature === 36.5
            && $dto->maskStatus === 1
            && $dto->photo === 'base64_encoded_image';
    });
});

test('handles missing optional fields gracefully', function () {
    $payload = [
        'info' => [
            'custom_id' => 'EMP001',
            'RecordID' => 'REC123',
            'time' => '2025-10-02 09:05:23',
            'similarity1' => 98.5,
            // Optional fields omitted
        ]
    ];

    Queue::fake();

    $handler = new MessageHandler();
    $handler->handleRecognitionEvent('mqtt/face/DEV001/Rec', $payload);

    Queue::assertPushed(ProcessAttendanceEvent::class, function ($job) {
        $dto = $job->event;

        return $dto->personId === null
            && $dto->personName === null
            && $dto->temperature === null
            && $dto->maskStatus === null
            && $dto->photo === null;
    });
});

test('throws exception for missing required field', function () {
    $payload = [
        'info' => [
            // Missing custom_id
            'RecordID' => 'REC123',
            'time' => '2025-10-02 09:05:23',
            'similarity1' => 98.5,
        ]
    ];

    $handler = new MessageHandler();
    $handler->handleRecognitionEvent('mqtt/face/DEV001/Rec', $payload);
})->throws(\InvalidArgumentException::class);

test('parses multiple timestamp formats', function ($timeString) {
    $payload = [
        'info' => [
            'custom_id' => 'EMP001',
            'RecordID' => 'REC123',
            'time' => $timeString,
            'similarity1' => 98.5,
        ]
    ];

    Queue::fake();

    $handler = new MessageHandler();
    $handler->handleRecognitionEvent('mqtt/face/DEV001/Rec', $payload);

    Queue::assertPushed(ProcessAttendanceEvent::class);
})->with([
    '2025-10-02 09:05:23',
    '2025/10/02 09:05:23',
    '2025-10-02T09:05:23',
    '2025-10-02T09:05:23+08:00',
]);
```

#### Integration Tests - Full MQTT Flow

**File:** `tests/Feature/MQTTRecognitionFlowTest.php`

```php
test('processes complete MQTT recognition event with all fields', function () {
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);

    $employee = Employee::factory()->create(['custom_id' => 'EMP001']);
    $device = Device::factory()->create(['device_id' => 'DEV001']);

    $payload = [
        'info' => [
            'custom_id' => 'EMP001',
            'RecordID' => 'REC123456',
            'time' => '2025-10-02 09:05:23',
            'similarity1' => 98.5,
            'personId' => '12345',
            'personName' => 'John Doe',
            'facesluiceName' => 'Main Entrance',
            'VerifyStatus' => '1',
            'temperature' => 36.5,
            'mask_status' => 1,
            'pic' => 'base64_image_data',
        ]
    ];

    $handler = app(MessageHandler::class);
    $handler->handleRecognitionEvent('mqtt/face/DEV001/Rec', $payload);

    // Verify attendance record created with all fields
    $record = AttendanceRecord::where('record_id', 'REC123456')->first();

    expect($record)->not->toBeNull();
    expect($record->employee_id)->toBe($employee->id);
    expect($record->similarity_score)->toBe(98.5);
    expect($record->device_name)->toBe('Main Entrance');
    expect($record->temperature)->toBe(36.5);
    expect($record->mask_status)->toBe(1);

    tenancy()->end();
});

test('logs warning for unrecognized employee with metadata', function () {
    Log::spy();

    $payload = [
        'info' => [
            'custom_id' => 'UNKNOWN',
            'RecordID' => 'REC999',
            'time' => '2025-10-02 09:05:23',
            'similarity1' => 95.0,
            'personName' => 'Unknown Person',
        ]
    ];

    $handler = app(MessageHandler::class);
    $handler->handleRecognitionEvent('mqtt/face/DEV001/Rec', $payload);

    Log::shouldHaveReceived('warning')
        ->with('Employee not found for recognition event', Mockery::on(function ($context) {
            return $context['custom_id'] === 'UNKNOWN'
                && $context['record_id'] === 'REC999';
        }));
});
```

### 6. Logging for Missing/Invalid Fields

**Logging Strategy:**

1. **Required Field Missing** → `Log::error()` with full payload context
2. **Optional Field Missing** → `Log::debug()` for observability (not an error)
3. **Invalid Field Type** → `Log::warning()` with expected vs. actual type
4. **Invalid Field Value** → `Log::warning()` with validation rule violation
5. **Successful Processing** → `Log::info()` with key field summary

**Example Logging Implementation:**

```php
// In MessageHandler::extractRequiredField()
if (!array_key_exists($key, $data)) {
    Log::error("Required MQTT field missing", [
        'field' => $key,
        'available_fields' => array_keys($data),
        'payload' => $data,
    ]);
    throw new \InvalidArgumentException("Required field '{$key}' is missing");
}

// In MessageHandler::extractOptionalField()
if (!array_key_exists($key, $data)) {
    Log::debug("Optional MQTT field not present", [
        'field' => $key,
        'device_id' => $deviceId ?? 'unknown',
    ]);
    return null;
}

// In ProcessAttendanceEvent::handle()
Log::info('MQTT attendance event processed successfully', [
    'employee_id' => $employee->id,
    'custom_id' => $this->event->customId,
    'similarity_score' => $this->event->similarityScore,
    'has_temperature' => $this->event->temperature !== null,
    'has_mask_status' => $this->event->maskStatus !== null,
    'has_photo' => $this->event->photo !== null,
    'processing_time_ms' => microtime(true) - $startTime,
]);
```

## Approach

### Implementation Phases

**Phase 1: Audit Current Implementation**
1. Review existing `AttendanceEventDTO` (if exists) for missing fields
2. Review `MessageHandler` field extraction logic
3. Review `ProcessAttendanceEvent` job field usage
4. Identify gaps in field coverage

**Phase 2: Update DTO and Extraction**
1. Add missing fields to `AttendanceEventDTO`
2. Implement field validation in DTO constructor
3. Update `MessageHandler::createAttendanceEventDTO()` to extract all fields
4. Add logging for missing/invalid fields

**Phase 3: Update Database Schema (if needed)**
1. Add migration for new optional fields in `attendance_records` table
2. Update `AttendanceRecord` model fillable/casts properties

**Phase 4: Implement Tests**
1. Write unit tests for DTO validation
2. Write unit tests for MessageHandler field extraction
3. Write integration tests for full MQTT → Job flow
4. Write edge case tests for missing/invalid fields

**Phase 5: Documentation**
1. Create MQTT payload reference table
2. Document field mapping in code comments
3. Update CLAUDE.md with field usage guidelines

### Verification Checklist

- [ ] All 11 MQTT fields extracted from payload
- [ ] AttendanceEventDTO contains all 11 fields with correct types
- [ ] Required vs. optional fields clearly defined
- [ ] Field validation implemented with appropriate exceptions
- [ ] Logging implemented for all validation failures
- [ ] Unit tests cover all field extraction scenarios
- [ ] Integration tests verify end-to-end field flow
- [ ] Documentation updated with field mapping table
- [ ] Code review confirms no field gaps

## External Dependencies

**None required.** This spec uses existing dependencies:
- Laravel framework (validation, logging)
- Pest PHP (testing)
- Existing MQTT client infrastructure
- Existing tenancy infrastructure

**No new packages or external services needed.**
