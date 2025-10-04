<?php

namespace App\Jobs;

use App\DTOs\Tenant as TenantDTO;
use App\Models\Tenant as TenantModel;
use App\Models\Tenant\Device;
use App\Models\Tenant\DeviceEnrollment;
use App\Models\Tenant\Employee;
use App\Services\MQTT\MQTTClient;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncEmployeeToDevice implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $employeeId,
        public int $deviceId,
        public string $tenantId,
        public string $action = 'add'  // 'add' or 'edit'
    ) {
        // Set the queue for this job
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(MQTTClient $mqttClient, TenantDatabaseManager $manager): void
    {
        try {
            // Step 1: Get tenant from central database
            $tenant = TenantModel::on(config('database.default'))
                ->where('id', $this->tenantId)
                ->where('is_active', true)
                ->first();

            if (! $tenant) {
                Log::channel('mqtt')->warning('Tenant not found or inactive', [
                    'tenant_id' => $this->tenantId,
                ]);

                return;
            }

            // Step 2: Setup tenant database connection
            $tenantDto = new TenantDTO(
                id: $tenant->id,
                company_name: $tenant->company_name,
                subdomain: $tenant->subdomain,
                domain: $tenant->domain,
                database_name: $tenant->database_name,
                database_host: $tenant->database_host,
                subscription_plan: $tenant->subscription_plan,
                max_employees: $tenant->max_employees,
                max_devices: $tenant->max_devices,
                is_active: $tenant->is_active,
            );

            $manager->setupTenantConnection($tenantDto);

            // Step 3: Get employee from tenant database
            $employee = Employee::on('tenant')
                ->where('id', $this->employeeId)
                ->where('is_active', true)
                ->first();

            if (! $employee) {
                Log::channel('mqtt')->warning('Employee not found or inactive', [
                    'employee_id' => $this->employeeId,
                    'tenant_id' => $this->tenantId,
                ]);

                return;
            }

            // Step 4: Get the specific device
            $device = Device::on('tenant')
                ->where('id', $this->deviceId)
                ->where('is_active', true)
                ->first();

            if (! $device) {
                Log::channel('mqtt')->warning('Device not found or inactive', [
                    'device_id' => $this->deviceId,
                    'tenant_id' => $this->tenantId,
                ]);

                return;
            }

            // Step 5: Check if enrollment exists
            $enrollment = DeviceEnrollment::on('tenant')
                ->where('employee_id', $employee->id)
                ->where('device_id', $device->id)
                ->first();

            // Determine the MQTT command based on action and enrollment status
            $command = $this->action === 'edit' || $enrollment ? 'EditPerson' : 'AddPerson';

            // Create or update device enrollment record
            DeviceEnrollment::on('tenant')->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'device_id' => $device->id,
                ],
                [
                    'enrollment_status' => 'pending',
                    'synced_at' => null,
                ]
            );

            // Prepare MQTT payload
            // Use JSON_UNESCAPED_SLASHES to prevent escaping base64 slashes
            $payload = json_encode([
                'messageId' => uniqid('sync_', true),
                'operator' => $command,
                'info' => [
                    'customId' => $employee->custom_id,
                    'name' => $employee->full_name,
                    'nation' => 1,
                    'gender' => $employee->gender ?? 0,
                    'birthday' => $employee->date_of_birth ? $employee->date_of_birth->format('Y-m-d') : '',
                    'address' => $employee->address ?? '',
                    'idCard' => $employee->national_id ?? '',
                    'tempCardType' => 0,
                    'EffectNumber' => 0,
                    'cardValidBegin' => '',
                    'cardValidEnd' => '',
                    'telnum1' => $employee->phone ?? '',
                    'native' => '',
                    'cardType2' => 0,
                    'cardNum2' => '',
                    'notes' => '',
                    'personType' => 0,
                    'cardType' => 0,
                    'strategyInfo' => [
                        'strategyNum' => 1,
                        'strategyData' => [
                            ['strategyID' => '1', 'strategyName' => 'default']
                        ]
                    ],
                    'pic' => $this->getEmployeePhotoBase64($employee),
                ]
            ], JSON_UNESCAPED_SLASHES);

            // Publish to device-specific topic
            // Use QoS 0 for large payloads to avoid socket issues
            $topic = "mqtt/face/{$device->device_id}";
            $mqttClient->publish($topic, $payload, 0);

            Log::channel('mqtt')->info('Employee synced to device', [
                'employee_id' => $employee->id,
                'employee_custom_id' => $employee->custom_id,
                'device_id' => $device->device_id,
                'command' => $command,
                'topic' => $topic,
            ]);

        } catch (\Exception $e) {
            // Update enrollment status to failed
            if (isset($employee) && isset($device)) {
                DeviceEnrollment::on('tenant')->updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'device_id' => $device->id,
                    ],
                    [
                        'enrollment_status' => 'failed',
                    ]
                );
            }

            Log::channel('mqtt')->error('Failed to sync employee to device', [
                'employee_id' => $this->employeeId,
                'device_id' => $this->deviceId,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get employee photo as base64 encoded data URI
     *
     * Resizes image to max 1920x1920px and optimizes for MQTT transmission
     */
    protected function getEmployeePhotoBase64(Employee $employee): string
    {
        if (!$employee->avatar) {
            return '';
        }

        try {
            // Avatar is stored in public disk (storage/app/public/avatars/...)
            $photoPath = storage_path('app/public/' . $employee->avatar);

            if (!file_exists($photoPath)) {
                Log::channel('mqtt')->warning('Employee photo file not found', [
                    'employee_id' => $employee->id,
                    'avatar_path' => $employee->avatar,
                    'full_path' => $photoPath,
                ]);
                return '';
            }

            // Load image and resize for MQTT transmission (devices typically need smaller images)
            $imageInfo = getimagesize($photoPath);
            $mimeType = $imageInfo['mime'];

            // Create image resource based on type
            $image = match($mimeType) {
                'image/jpeg' => imagecreatefromjpeg($photoPath),
                'image/png' => imagecreatefrompng($photoPath),
                'image/gif' => imagecreatefromgif($photoPath),
                default => throw new \Exception("Unsupported image type: {$mimeType}"),
            };

            // Handle PNG transparency - create white background
            if ($mimeType === 'image/png') {
                $width = imagesx($image);
                $height = imagesy($image);
                $newImage = imagecreatetruecolor($width, $height);
                $white = imagecolorallocate($newImage, 255, 255, 255);
                imagefill($newImage, 0, 0, $white);
                imagecopy($newImage, $image, 0, 0, 0, 0, $width, $height);
                imagedestroy($image);
                $image = $newImage;
            }

            // Calculate new dimensions based on device requirements
            // Device max: 1080P (1920x1080), but smaller is better for MQTT
            // We'll use 800px max to balance quality and message size
            $maxDimension = 800;
            $width = imagesx($image);
            $height = imagesy($image);
            $finalWidth = $width;
            $finalHeight = $height;

            if ($width > $maxDimension || $height > $maxDimension) {
                $ratio = min($maxDimension / $width, $maxDimension / $height);
                $newWidth = (int)($width * $ratio);
                $newHeight = (int)($height * $ratio);

                $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imagedestroy($image);
                $image = $resizedImage;
                $finalWidth = $newWidth;
                $finalHeight = $newHeight;
            }

            // Convert to JPEG with compression (quality 85 - balance between size and quality)
            // Device requires 50K-200K file size
            ob_start();
            imagejpeg($image, null, 85);
            $imageData = ob_get_clean();
            imagedestroy($image);

            $base64 = base64_encode($imageData);

            Log::channel('mqtt')->debug('Employee photo encoded', [
                'employee_id' => $employee->id,
                'original_size' => filesize($photoPath),
                'optimized_size' => strlen($imageData),
                'base64_size' => strlen($base64),
                'original_dimensions' => "{$width}x{$height}",
                'final_dimensions' => "{$finalWidth}x{$finalHeight}",
            ]);

            // Return just base64 string without data URI prefix (devices may not need it)
            return $base64;
        } catch (\Exception $e) {
            Log::channel('mqtt')->warning('Failed to encode employee photo', [
                'employee_id' => $employee->id,
                'avatar_path' => $employee->avatar ?? 'null',
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }
}
