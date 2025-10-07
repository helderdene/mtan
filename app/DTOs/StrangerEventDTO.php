<?php

namespace App\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

class StrangerEventDTO
{
    public function __construct(
        public string $device_id,               // Required: Device ID from topic
        public DateTimeImmutable $detected_at,  // Required: Detection timestamp
        public string $photo_base64,            // Required: Base64-encoded photo
        public float $similarity_score,         // Required: Match score (typically < 60 for strangers)
        public string $record_id,               // Required: Device-generated unique record ID
        public ?string $person_name = null,     // Optional: Name shown on device
        public ?string $device_name = null,     // Optional: Device location name
        public ?float $temperature = null,      // Optional: Body temperature
        public ?int $mask_status = null,        // Optional: Mask detection status
    ) {
        $this->validateRequiredFields();
    }

    /**
     * Create DTO from MQTT payload
     */
    public static function fromMqttPayload(string $topic, string $payload): self
    {
        // Extract device_id from topic (format: mqtt/face/{device_id}/Stranger)
        $deviceId = self::extractDeviceIdFromTopic($topic);

        // Parse JSON payload
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON payload: '.json_last_error_msg());
        }

        // Handle device payload format: {operator: "StrangerPush", info: {...}}
        if (isset($data['operator']) && isset($data['info'])) {
            $info = $data['info'];

            // Parse timestamp
            $timestamp = self::parseTimestamp($info['time'] ?? null);

            // Extract required fields
            $recordId = $info['RecordID'] ?? $info['recordId'] ?? null;
            if (! $recordId) {
                throw new InvalidArgumentException('Missing required field: RecordID');
            }

            // Extract similarity score
            $similarity = isset($info['similarity1'])
                ? (float) $info['similarity1']
                : 0.0;

            // Extract photo (base64 encoded)
            $photoBase64 = $info['pic'] ?? null;
            if (! $photoBase64) {
                throw new InvalidArgumentException('Missing required field: pic (photo)');
            }

            return new self(
                device_id: $deviceId,
                detected_at: $timestamp,
                photo_base64: $photoBase64,
                similarity_score: $similarity,
                record_id: $recordId,
                person_name: $info['personName'] ?? null,
                device_name: $info['facesluiceName'] ?? null,
                temperature: isset($info['temperature']) ? (float) $info['temperature'] : null,
                mask_status: isset($info['mask_status']) ? (int) $info['mask_status'] : null,
            );
        }

        throw new InvalidArgumentException('Invalid stranger event payload format');
    }

    /**
     * Extract device ID from MQTT topic
     */
    protected static function extractDeviceIdFromTopic(string $topic): string
    {
        // Format: mqtt/face/{device_id}/Stranger
        if (preg_match('/mqtt\/face\/([^\/]+)\/Stranger/', $topic, $matches)) {
            return $matches[1];
        }

        throw new InvalidArgumentException("Invalid topic format: {$topic}");
    }

    /**
     * Parse timestamp from device
     */
    protected static function parseTimestamp(?string $timestamp): DateTimeImmutable
    {
        if (! $timestamp) {
            return new DateTimeImmutable;
        }

        // Try multiple formats
        $formats = [
            'Y-m-d H:i:s',       // 2025-10-07 15:30:45
            'Y-m-d\TH:i:s',      // 2025-10-07T15:30:45
            'Y-m-d\TH:i:s.uP',   // 2025-10-07T15:30:45.123456+00:00
        ];

        foreach ($formats as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $timestamp);
            if ($parsed !== false) {
                return $parsed;
            }
        }

        // Fallback to strtotime
        $parsed = DateTimeImmutable::createFromFormat('U', (string) strtotime($timestamp));
        if ($parsed !== false) {
            return $parsed;
        }

        throw new InvalidArgumentException("Unable to parse timestamp: {$timestamp}");
    }

    /**
     * Validate required fields
     */
    protected function validateRequiredFields(): void
    {
        if (empty($this->device_id)) {
            throw new InvalidArgumentException('device_id is required');
        }

        if (empty($this->photo_base64)) {
            throw new InvalidArgumentException('photo_base64 is required');
        }

        if (empty($this->record_id)) {
            throw new InvalidArgumentException('record_id is required');
        }
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'device_id' => $this->device_id,
            'detected_at' => $this->detected_at->format('Y-m-d H:i:s'),
            'photo_base64' => substr($this->photo_base64, 0, 50).'...', // Truncate for logging
            'similarity_score' => $this->similarity_score,
            'record_id' => $this->record_id,
            'person_name' => $this->person_name,
            'device_name' => $this->device_name,
            'temperature' => $this->temperature,
            'mask_status' => $this->mask_status,
        ];
    }
}
