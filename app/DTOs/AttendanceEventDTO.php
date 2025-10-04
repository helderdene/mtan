<?php

namespace App\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

class AttendanceEventDTO
{
    public function __construct(
        public string $device_id,           // Required: Device ID from topic
        public string $custom_id,           // Required: Employee custom ID
        public string $record_id,           // Required: Device-generated unique record ID
        public DateTimeImmutable $timestamp, // Required: Recognition timestamp
        public float $similarity_score,     // Required: Biometric match score (0-100)
        public string $event_type,          // Required: Event type (recognition, stranger, ack)
        public ?string $person_id = null,   // Optional: Device-internal person ID
        public ?string $person_name = null, // Optional: Employee name on device
        public ?string $device_name = null, // Optional: Device location name
        public ?string $verify_status = null, // Optional: Device verification status
        public ?float $temperature = null,  // Optional: Body temperature (30-45°C)
        public ?int $mask_status = null,    // Optional: Mask detection (0=no mask, 1=has mask)
        public ?string $photo_base64 = null, // Optional: Base64-encoded photo
    ) {
        $this->validateRequiredFields();
        $this->validateDataTypes();
    }

    /**
     * Create DTO from MQTT payload
     */
    public static function fromMqttPayload(string $topic, string $payload): self
    {
        // Extract device_id from topic (format: mqtt/face/{device_id}/Rec)
        $deviceId = self::extractDeviceIdFromTopic($topic);
        $eventType = self::extractEventTypeFromTopic($topic);

        // Parse JSON payload
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON payload: ' . json_last_error_msg());
        }

        // Handle device payload format: {operator: "RecPush", info: {...}}
        if (isset($data['operator']) && isset($data['info'])) {
            $info = $data['info'];

            // Parse timestamp with multiple format support
            $timestamp = self::parseTimestamp($info['time'] ?? null);

            // Extract required fields
            $customId = self::extractRequiredField($info, 'customId', 'custom_id');
            $recordId = self::extractRequiredField($info, 'RecordID', 'record_id');

            // Extract similarity score (0-100 range from device)
            $similarity = isset($info['similarity1'])
                ? (float) $info['similarity1']
                : null;

            if ($similarity === null) {
                throw new InvalidArgumentException('Missing required field: similarity1');
            }

            // Extract optional fields
            $personId = self::extractOptionalField($info, 'personId');
            $personName = self::extractOptionalField($info, 'personName');
            $deviceName = self::extractOptionalField($info, 'facesluiceName');
            $verifyStatus = self::extractOptionalField($info, 'VerifyStatus');

            // Temperature (optional, float)
            $temperature = isset($info['temperature'])
                ? (float) $info['temperature']
                : null;

            // Mask status mapping:
            // Device sends: isNoMask="0" (has mask) or isNoMask="1" (no mask)
            // We store: mask_status=1 (has mask) or mask_status=0 (no mask)
            $maskStatus = null;
            if (isset($info['isNoMask'])) {
                $maskStatus = ($info['isNoMask'] === '0' || $info['isNoMask'] === 0) ? 1 : 0;
            } elseif (isset($info['mask_status'])) {
                $maskStatus = (int) $info['mask_status'];
            }

            // Photo (base64 encoded)
            $photoBase64 = self::extractOptionalField($info, 'pic');

            return new self(
                device_id: $deviceId,
                custom_id: $customId,
                record_id: $recordId,
                timestamp: $timestamp,
                similarity_score: $similarity,
                event_type: $eventType,
                person_id: $personId,
                person_name: $personName,
                device_name: $deviceName,
                verify_status: $verifyStatus,
                temperature: $temperature,
                mask_status: $maskStatus,
                photo_base64: $photoBase64,
            );
        }

        // Legacy format support (for testing/backward compatibility)
        self::validateLegacyRequiredFields($data, ['timestamp', 'custom_id']);

        $timestamp = self::parseTimestamp($data['timestamp']);

        return new self(
            device_id: $deviceId,
            custom_id: $data['custom_id'],
            record_id: $data['record_id'] ?? uniqid('REC_', true),
            timestamp: $timestamp,
            similarity_score: $data['similarity'] ?? 0,
            event_type: $eventType,
            person_id: $data['person_id'] ?? null,
            person_name: $data['person_name'] ?? null,
            device_name: $data['device_name'] ?? null,
            verify_status: $data['status'] ?? null,
            temperature: $data['temperature'] ?? null,
            mask_status: $data['mask'] ?? null,
            photo_base64: $data['image_url'] ?? null,
        );
    }

    /**
     * Extract required field from payload with error handling
     */
    protected static function extractRequiredField(array $data, string $key, string $fieldName): string
    {
        if (! isset($data[$key]) || empty($data[$key])) {
            \Log::channel('mqtt')->error('Missing required MQTT field', [
                'field_name' => $fieldName,
                'payload_key' => $key,
                'available_keys' => array_keys($data),
            ]);

            throw new InvalidArgumentException("Missing required field: {$fieldName} (payload key: {$key})");
        }

        return (string) $data[$key];
    }

    /**
     * Extract optional field from payload with debug logging
     */
    protected static function extractOptionalField(array $data, string $key): ?string
    {
        if (! isset($data[$key])) {
            \Log::channel('mqtt')->debug('Optional MQTT field not present', [
                'field_name' => $key,
            ]);

            return null;
        }

        return $data[$key] !== '' ? (string) $data[$key] : null;
    }

    /**
     * Parse timestamp with multiple format support
     */
    protected static function parseTimestamp(?string $timestamp): DateTimeImmutable
    {
        if (empty($timestamp)) {
            throw new InvalidArgumentException('Missing required field: timestamp');
        }

        // Try multiple timestamp formats
        $formats = [
            'Y-m-d H:i:s',
            'Y/m/d H:i:s',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i:s\Z',
            'Y-m-d\TH:i:sP',
        ];

        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $timestamp);
            if ($parsed !== false) {
                return $parsed;
            }
        }

        // Fallback to strtotime for flexibility
        $unixTimestamp = strtotime($timestamp);
        if ($unixTimestamp !== false) {
            $result = DateTimeImmutable::createFromFormat('U', (string) $unixTimestamp);
            if ($result !== false) {
                return $result;
            }
        }

        throw new InvalidArgumentException("Invalid timestamp format: {$timestamp}");
    }

    /**
     * Extract device ID from MQTT topic
     */
    protected static function extractDeviceIdFromTopic(string $topic): string
    {
        // Support multiple topic formats:
        // - mqtt/face/{device_id}/Rec (actual device format)
        // - device/{device_id}/{event_type} (legacy format)

        // Use regex for more robust extraction
        if (preg_match('#^mqtt/face/([^/]+)/(Rec|Stranger|Ack)$#', $topic, $matches)) {
            return $matches[1];
        }

        if (preg_match('#^device/([^/]+)/([^/]+)$#', $topic, $matches)) {
            return $matches[1];
        }

        throw new InvalidArgumentException("Invalid topic format. Expected: mqtt/face/{device_id}/Rec or device/{device_id}/{event_type}. Got: {$topic}");
    }

    /**
     * Extract event type from MQTT topic
     */
    protected static function extractEventTypeFromTopic(string $topic): string
    {
        // Extract from mqtt/face/{device_id}/Rec format
        if (preg_match('#^mqtt/face/[^/]+/(Rec|Stranger|Ack)$#', $topic, $matches)) {
            return match ($matches[1]) {
                'Rec' => 'recognition',
                'Stranger' => 'stranger',
                'Ack' => 'ack',
                default => strtolower($matches[1]),
            };
        }

        // Extract from device/{device_id}/{event_type} format
        if (preg_match('#^device/[^/]+/([^/]+)$#', $topic, $matches)) {
            return $matches[1];
        }

        throw new InvalidArgumentException("Invalid topic format for event type extraction: {$topic}");
    }

    /**
     * Validate required fields are not empty
     */
    protected function validateRequiredFields(): void
    {
        // Validate custom_id
        if (empty($this->custom_id)) {
            throw new InvalidArgumentException('custom_id cannot be empty');
        }

        // Validate record_id
        if (empty($this->record_id)) {
            throw new InvalidArgumentException('record_id cannot be empty');
        }

        // Validate similarity_score range (0-100)
        if ($this->similarity_score < 0 || $this->similarity_score > 100) {
            throw new InvalidArgumentException("similarity_score must be between 0 and 100, got: {$this->similarity_score}");
        }
    }

    /**
     * Validate optional field data types and ranges
     */
    protected function validateDataTypes(): void
    {
        // Validate temperature range if present (30-45°C)
        if ($this->temperature !== null) {
            if ($this->temperature < 30 || $this->temperature > 45) {
                \Log::channel('mqtt')->warning('Temperature out of valid range', [
                    'temperature' => $this->temperature,
                    'custom_id' => $this->custom_id,
                    'device_id' => $this->device_id,
                ]);

                // Don't throw exception for out-of-range temperature, just log warning
                // This allows processing to continue even with invalid sensor readings
            }
        }

        // Validate mask_status is 0 or 1 if present
        if ($this->mask_status !== null && $this->mask_status !== 0 && $this->mask_status !== 1) {
            throw new InvalidArgumentException("mask_status must be 0 or 1, got: {$this->mask_status}");
        }
    }

    /**
     * Validate required fields for legacy format
     */
    protected static function validateLegacyRequiredFields(array $data, array $required): void
    {
        foreach ($required as $field) {
            if (! isset($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }
    }

    /**
     * Convert DTO to array
     */
    public function toArray(): array
    {
        return [
            'device_id' => $this->device_id,
            'custom_id' => $this->custom_id,
            'record_id' => $this->record_id,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s'),
            'similarity_score' => $this->similarity_score,
            'event_type' => $this->event_type,
            'person_id' => $this->person_id,
            'person_name' => $this->person_name,
            'device_name' => $this->device_name,
            'verify_status' => $this->verify_status,
            'temperature' => $this->temperature,
            'mask_status' => $this->mask_status,
            'photo_base64' => $this->photo_base64,
        ];
    }
}
