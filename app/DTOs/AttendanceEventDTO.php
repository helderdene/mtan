<?php

namespace App\DTOs;

class AttendanceEventDTO
{
    public function __construct(
        public string $device_id,
        public ?string $person_id,
        public string $timestamp,
        public string $event_type,
        public ?float $temperature = null,
        public ?bool $mask = null,
        public ?float $similarity = null,
        public ?string $image_url = null,
        public ?string $status = null,
    ) {
    }

    /**
     * Create DTO from MQTT payload
     */
    public static function fromMqttPayload(string $topic, string $payload): self
    {
        // Extract device_id from topic (format: device/{device_id}/{event_type})
        $deviceId = self::extractDeviceIdFromTopic($topic);
        $eventType = self::extractEventTypeFromTopic($topic);

        // Parse JSON payload
        $data = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON payload: ' . json_last_error_msg());
        }

        // Handle device payload format: {operator: "RecPush", info: {...}}
        if (isset($data['operator']) && isset($data['info'])) {
            $info = $data['info'];

            return new self(
                device_id: $deviceId,
                person_id: $info['customId'] ?? null,
                timestamp: $info['time'] ?? now()->toDateTimeString(),
                event_type: $eventType,
                temperature: isset($info['temperature']) ? (float) $info['temperature'] : null,
                mask: isset($info['isNoMask']) ? ($info['isNoMask'] === '0') : null,
                similarity: isset($info['similarity1']) ? (float) $info['similarity1'] / 100 : null,
                image_url: $info['pic'] ?? null,
                status: $info['VerifyStatus'] ?? null,
            );
        }

        // Legacy format support
        self::validateRequiredFields($data, ['timestamp']);

        // Validate similarity score if present
        if (isset($data['similarity'])) {
            if ($data['similarity'] < 0 || $data['similarity'] > 1) {
                throw new \InvalidArgumentException('Similarity score must be between 0 and 1');
            }
        }

        return new self(
            device_id: $deviceId,
            person_id: $data['person_id'] ?? null,
            timestamp: $data['timestamp'],
            event_type: $eventType,
            temperature: $data['temperature'] ?? null,
            mask: $data['mask'] ?? null,
            similarity: $data['similarity'] ?? null,
            image_url: $data['image_url'] ?? null,
            status: $data['status'] ?? null,
        );
    }

    /**
     * Extract device ID from MQTT topic
     */
    protected static function extractDeviceIdFromTopic(string $topic): string
    {
        // Support multiple topic formats:
        // - mqtt/face/{device_id}/Rec (actual device format)
        // - device/{device_id}/{event_type} (legacy format)
        $parts = explode('/', $topic);

        if (count($parts) !== 4 && count($parts) !== 3) {
            throw new \InvalidArgumentException('Invalid topic format. Expected: mqtt/face/{device_id}/Rec or device/{device_id}/{event_type}');
        }

        // mqtt/face/{device_id}/Rec format
        if (count($parts) === 4 && $parts[0] === 'mqtt' && $parts[1] === 'face') {
            return $parts[2];
        }

        // device/{device_id}/{event_type} format
        if (count($parts) === 3 && $parts[0] === 'device') {
            return $parts[1];
        }

        throw new \InvalidArgumentException('Invalid topic format. Expected: mqtt/face/{device_id}/Rec or device/{device_id}/{event_type}');
    }

    /**
     * Extract event type from MQTT topic
     */
    protected static function extractEventTypeFromTopic(string $topic): string
    {
        // Support multiple topic formats:
        // - mqtt/face/{device_id}/Rec (actual device format)
        // - device/{device_id}/{event_type} (legacy format)
        $parts = explode('/', $topic);

        if (count($parts) === 4 && $parts[0] === 'mqtt' && $parts[1] === 'face') {
            // Map device event types to system event types
            return match ($parts[3]) {
                'Rec' => 'recognition',
                'Stranger' => 'stranger',
                'Ack' => 'ack',
                default => strtolower($parts[3]),
            };
        }

        if (count($parts) === 3 && $parts[0] === 'device') {
            return $parts[2];
        }

        throw new \InvalidArgumentException('Invalid topic format. Expected: mqtt/face/{device_id}/Rec or device/{device_id}/{event_type}');
    }

    /**
     * Validate required fields are present
     */
    protected static function validateRequiredFields(array $data, array $required): void
    {
        foreach ($required as $field) {
            if (! isset($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
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
            'person_id' => $this->person_id,
            'timestamp' => $this->timestamp,
            'event_type' => $this->event_type,
            'temperature' => $this->temperature,
            'mask' => $this->mask,
            'similarity' => $this->similarity,
            'image_url' => $this->image_url,
            'status' => $this->status,
        ];
    }
}
