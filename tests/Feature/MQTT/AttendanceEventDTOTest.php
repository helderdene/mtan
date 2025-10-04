<?php

use App\DTOs\AttendanceEventDTO;

describe('AttendanceEventDTO', function () {
    test('can create from MQTT payload for recognition event', function () {
        $payload = json_encode([
            'person_id' => 'EMP001',
            'timestamp' => '2025-10-02 10:30:45',
            'temperature' => 36.5,
            'mask' => true,
            'similarity' => 0.9850,
        ]);

        $topic = 'device/DEVICE001/recognition';

        $dto = AttendanceEventDTO::fromMqttPayload($topic, $payload);

        expect($dto)->toBeInstanceOf(AttendanceEventDTO::class);
        expect($dto->device_id)->toBe('DEVICE001');
        expect($dto->person_id)->toBe('EMP001');
        expect($dto->timestamp)->toBe('2025-10-02 10:30:45');
        expect($dto->temperature)->toBe(36.5);
        expect($dto->mask)->toBeTrue();
        expect($dto->similarity)->toBe(0.9850);
        expect($dto->event_type)->toBe('recognition');
    });

    test('can create from MQTT payload for stranger event', function () {
        $payload = json_encode([
            'timestamp' => '2025-10-02 11:15:30',
            'temperature' => 37.2,
            'mask' => false,
            'image_url' => '/storage/strangers/20251002_111530.jpg',
        ]);

        $topic = 'device/DEVICE002/stranger';

        $dto = AttendanceEventDTO::fromMqttPayload($topic, $payload);

        expect($dto)->toBeInstanceOf(AttendanceEventDTO::class);
        expect($dto->device_id)->toBe('DEVICE002');
        expect($dto->person_id)->toBeNull();
        expect($dto->timestamp)->toBe('2025-10-02 11:15:30');
        expect($dto->temperature)->toBe(37.2);
        expect($dto->mask)->toBeFalse();
        expect($dto->image_url)->toBe('/storage/strangers/20251002_111530.jpg');
        expect($dto->event_type)->toBe('stranger');
    });

    test('extracts device_id from topic with multiple segments', function () {
        $payload = json_encode([
            'person_id' => 'EMP002',
            'timestamp' => '2025-10-02 12:00:00',
        ]);

        $topic = 'device/DEVICE-ABC-123/recognition';

        $dto = AttendanceEventDTO::fromMqttPayload($topic, $payload);

        expect($dto->device_id)->toBe('DEVICE-ABC-123');
    });

    test('parses JSON payload correctly', function () {
        $payload = '{"person_id":"EMP003","timestamp":"2025-10-02 13:45:00","similarity":0.95}';
        $topic = 'device/DEVICE003/recognition';

        $dto = AttendanceEventDTO::fromMqttPayload($topic, $payload);

        expect($dto->person_id)->toBe('EMP003');
        expect($dto->similarity)->toBe(0.95);
    });

    test('handles missing optional fields gracefully', function () {
        $payload = json_encode([
            'person_id' => 'EMP004',
            'timestamp' => '2025-10-02 14:00:00',
        ]);

        $topic = 'device/DEVICE004/recognition';

        $dto = AttendanceEventDTO::fromMqttPayload($topic, $payload);

        expect($dto->person_id)->toBe('EMP004');
        expect($dto->temperature)->toBeNull();
        expect($dto->mask)->toBeNull();
        expect($dto->image_url)->toBeNull();
    });

    test('throws exception for invalid JSON payload', function () {
        $payload = 'invalid-json{';
        $topic = 'device/DEVICE005/recognition';

        expect(fn () => AttendanceEventDTO::fromMqttPayload($topic, $payload))
            ->toThrow(\InvalidArgumentException::class, 'Invalid JSON payload');
    });

    test('throws exception for missing required fields', function () {
        $payload = json_encode([
            'temperature' => 36.5,
        ]);

        $topic = 'device/DEVICE006/recognition';

        expect(fn () => AttendanceEventDTO::fromMqttPayload($topic, $payload))
            ->toThrow(\InvalidArgumentException::class, 'Missing required field: timestamp');
    });

    test('throws exception for invalid topic format', function () {
        $payload = json_encode([
            'person_id' => 'EMP005',
            'timestamp' => '2025-10-02 15:00:00',
        ]);

        $topic = 'invalid/topic/format';

        expect(fn () => AttendanceEventDTO::fromMqttPayload($topic, $payload))
            ->toThrow(\InvalidArgumentException::class, 'Invalid topic format');
    });

    test('can convert to array', function () {
        $payload = json_encode([
            'person_id' => 'EMP006',
            'timestamp' => '2025-10-02 16:00:00',
            'temperature' => 36.8,
            'mask' => true,
            'similarity' => 0.98,
        ]);

        $topic = 'device/DEVICE007/recognition';

        $dto = AttendanceEventDTO::fromMqttPayload($topic, $payload);
        $array = $dto->toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveKeys(['device_id', 'person_id', 'timestamp', 'temperature', 'mask', 'similarity', 'event_type']);
        expect($array['device_id'])->toBe('DEVICE007');
        expect($array['person_id'])->toBe('EMP006');
    });

    test('validates similarity score range', function () {
        $payload = json_encode([
            'person_id' => 'EMP007',
            'timestamp' => '2025-10-02 17:00:00',
            'similarity' => 1.5, // Invalid: > 1.0
        ]);

        $topic = 'device/DEVICE008/recognition';

        expect(fn () => AttendanceEventDTO::fromMqttPayload($topic, $payload))
            ->toThrow(\InvalidArgumentException::class, 'Similarity score must be between 0 and 1');
    });

    test('determines event type from topic', function () {
        $recognitionPayload = json_encode(['person_id' => 'EMP008', 'timestamp' => '2025-10-02 18:00:00']);
        $strangerPayload = json_encode(['timestamp' => '2025-10-02 18:00:00']);
        $ackPayload = json_encode(['status' => 'success', 'timestamp' => '2025-10-02 18:00:00']);

        $recognitionDto = AttendanceEventDTO::fromMqttPayload('device/DEV001/recognition', $recognitionPayload);
        $strangerDto = AttendanceEventDTO::fromMqttPayload('device/DEV002/stranger', $strangerPayload);
        $ackDto = AttendanceEventDTO::fromMqttPayload('device/DEV003/ack', $ackPayload);

        expect($recognitionDto->event_type)->toBe('recognition');
        expect($strangerDto->event_type)->toBe('stranger');
        expect($ackDto->event_type)->toBe('ack');
    });
});
