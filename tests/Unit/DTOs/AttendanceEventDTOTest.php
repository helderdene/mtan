<?php

use App\DTOs\AttendanceEventDTO;
use Illuminate\Support\Facades\Log;

describe('AttendanceEventDTO', function () {
    test('creates DTO with all required fields', function () {
        $dto = new AttendanceEventDTO(
            device_id: 'device001',
            custom_id: 'EMP001',
            record_id: 'REC123456',
            timestamp: new DateTimeImmutable('2025-10-04 09:00:00'),
            similarity_score: 95.5,
            event_type: 'recognition',
        );

        expect($dto->device_id)->toBe('device001')
            ->and($dto->custom_id)->toBe('EMP001')
            ->and($dto->record_id)->toBe('REC123456')
            ->and($dto->similarity_score)->toBe(95.5)
            ->and($dto->event_type)->toBe('recognition');
    });

    test('creates DTO with all optional fields', function () {
        $dto = new AttendanceEventDTO(
            device_id: 'device001',
            custom_id: 'EMP001',
            record_id: 'REC123456',
            timestamp: new DateTimeImmutable('2025-10-04 09:00:00'),
            similarity_score: 95.5,
            event_type: 'recognition',
            person_id: 'P12345',
            person_name: 'John Doe',
            device_name: 'Main Entrance',
            verify_status: '1',
            temperature: 36.5,
            mask_status: 1,
            photo_base64: 'base64encodedphoto',
        );

        expect($dto->person_id)->toBe('P12345')
            ->and($dto->person_name)->toBe('John Doe')
            ->and($dto->device_name)->toBe('Main Entrance')
            ->and($dto->verify_status)->toBe('1')
            ->and($dto->temperature)->toBe(36.5)
            ->and($dto->mask_status)->toBe(1)
            ->and($dto->photo_base64)->toBe('base64encodedphoto');
    });

    test('throws exception for empty custom_id', function () {
        new AttendanceEventDTO(
            device_id: 'device001',
            custom_id: '',
            record_id: 'REC123456',
            timestamp: new DateTimeImmutable('2025-10-04 09:00:00'),
            similarity_score: 95.5,
            event_type: 'recognition',
        );
    })->throws(InvalidArgumentException::class, 'custom_id cannot be empty');

    test('throws exception for empty record_id', function () {
        new AttendanceEventDTO(
            device_id: 'device001',
            custom_id: 'EMP001',
            record_id: '',
            timestamp: new DateTimeImmutable('2025-10-04 09:00:00'),
            similarity_score: 95.5,
            event_type: 'recognition',
        );
    })->throws(InvalidArgumentException::class, 'record_id cannot be empty');

    test('throws exception for similarity_score below 0', function () {
        new AttendanceEventDTO(
            device_id: 'device001',
            custom_id: 'EMP001',
            record_id: 'REC123456',
            timestamp: new DateTimeImmutable('2025-10-04 09:00:00'),
            similarity_score: -1,
            event_type: 'recognition',
        );
    })->throws(InvalidArgumentException::class, 'similarity_score must be between 0 and 100');

    test('throws exception for similarity_score above 100', function () {
        new AttendanceEventDTO(
            device_id: 'device001',
            custom_id: 'EMP001',
            record_id: 'REC123456',
            timestamp: new DateTimeImmutable('2025-10-04 09:00:00'),
            similarity_score: 101,
            event_type: 'recognition',
        );
    })->throws(InvalidArgumentException::class, 'similarity_score must be between 0 and 100');

    test('logs warning for temperature below 30°C', function () {
        Log::shouldReceive('channel')
            ->with('mqtt')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('warning')
            ->once()
            ->with('Temperature out of valid range', Mockery::type('array'));

        $dto = new AttendanceEventDTO(
            device_id: 'device001',
            custom_id: 'EMP001',
            record_id: 'REC123456',
            timestamp: new DateTimeImmutable('2025-10-04 09:00:00'),
            similarity_score: 95.5,
            event_type: 'recognition',
            temperature: 29.9,
        );

        expect($dto->temperature)->toBe(29.9);
    });

    test('logs warning for temperature above 45°C', function () {
        Log::shouldReceive('channel')
            ->with('mqtt')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('warning')
            ->once()
            ->with('Temperature out of valid range', Mockery::type('array'));

        $dto = new AttendanceEventDTO(
            device_id: 'device001',
            custom_id: 'EMP001',
            record_id: 'REC123456',
            timestamp: new DateTimeImmutable('2025-10-04 09:00:00'),
            similarity_score: 95.5,
            event_type: 'recognition',
            temperature: 45.1,
        );

        expect($dto->temperature)->toBe(45.1);
    });

    test('throws exception for invalid mask_status', function () {
        new AttendanceEventDTO(
            device_id: 'device001',
            custom_id: 'EMP001',
            record_id: 'REC123456',
            timestamp: new DateTimeImmutable('2025-10-04 09:00:00'),
            similarity_score: 95.5,
            event_type: 'recognition',
            mask_status: 2,
        );
    })->throws(InvalidArgumentException::class, 'mask_status must be 0 or 1');

    test('toArray includes all fields with correct format', function () {
        $timestamp = new DateTimeImmutable('2025-10-04 09:15:30');

        $dto = new AttendanceEventDTO(
            device_id: 'device001',
            custom_id: 'EMP001',
            record_id: 'REC123456',
            timestamp: $timestamp,
            similarity_score: 95.5,
            event_type: 'recognition',
            person_id: 'P12345',
            person_name: 'John Doe',
            device_name: 'Main Entrance',
            verify_status: '1',
            temperature: 36.5,
            mask_status: 1,
            photo_base64: 'base64photo',
        );

        $array = $dto->toArray();

        expect($array)->toBe([
            'device_id' => 'device001',
            'custom_id' => 'EMP001',
            'record_id' => 'REC123456',
            'timestamp' => '2025-10-04 09:15:30',
            'similarity_score' => 95.5,
            'event_type' => 'recognition',
            'person_id' => 'P12345',
            'person_name' => 'John Doe',
            'device_name' => 'Main Entrance',
            'verify_status' => '1',
            'temperature' => 36.5,
            'mask_status' => 1,
            'photo_base64' => 'base64photo',
        ]);
    });

    test('creates from MQTT payload with all fields', function () {
        $topic = 'mqtt/face/device001/Rec';
        $payload = json_encode([
            'operator' => 'RecPush',
            'info' => [
                'customId' => 'EMP001',
                'RecordID' => 'REC123456',
                'time' => '2025-10-04 09:15:30',
                'similarity1' => 95.5,
                'personId' => 'P12345',
                'personName' => 'John Doe',
                'facesluiceName' => 'Main Entrance',
                'VerifyStatus' => '1',
                'temperature' => 36.5,
                'isNoMask' => '0', // 0 = has mask
                'pic' => 'base64encodedphoto',
            ],
        ]);

        $dto = AttendanceEventDTO::fromMqttPayload($topic, $payload);

        expect($dto->device_id)->toBe('device001')
            ->and($dto->custom_id)->toBe('EMP001')
            ->and($dto->record_id)->toBe('REC123456')
            ->and($dto->timestamp->format('Y-m-d H:i:s'))->toBe('2025-10-04 09:15:30')
            ->and($dto->similarity_score)->toBe(95.5)
            ->and($dto->event_type)->toBe('recognition')
            ->and($dto->person_id)->toBe('P12345')
            ->and($dto->person_name)->toBe('John Doe')
            ->and($dto->device_name)->toBe('Main Entrance')
            ->and($dto->verify_status)->toBe('1')
            ->and($dto->temperature)->toBe(36.5)
            ->and($dto->mask_status)->toBe(1) // converted from isNoMask=0
            ->and($dto->photo_base64)->toBe('base64encodedphoto');
    });

    test('throws exception for missing customId in payload', function () {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('error')->once();

        $topic = 'mqtt/face/device001/Rec';
        $payload = json_encode([
            'operator' => 'RecPush',
            'info' => [
                'RecordID' => 'REC123456',
                'time' => '2025-10-04 09:15:30',
                'similarity1' => 95.5,
            ],
        ]);

        AttendanceEventDTO::fromMqttPayload($topic, $payload);
    })->throws(InvalidArgumentException::class, 'Missing required field: custom_id');

    test('throws exception for missing RecordID in payload', function () {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('error')->once();

        $topic = 'mqtt/face/device001/Rec';
        $payload = json_encode([
            'operator' => 'RecPush',
            'info' => [
                'customId' => 'EMP001',
                'time' => '2025-10-04 09:15:30',
                'similarity1' => 95.5,
            ],
        ]);

        AttendanceEventDTO::fromMqttPayload($topic, $payload);
    })->throws(InvalidArgumentException::class, 'Missing required field: record_id');

    test('throws exception for missing time in payload', function () {
        $topic = 'mqtt/face/device001/Rec';
        $payload = json_encode([
            'operator' => 'RecPush',
            'info' => [
                'customId' => 'EMP001',
                'RecordID' => 'REC123456',
                'similarity1' => 95.5,
            ],
        ]);

        AttendanceEventDTO::fromMqttPayload($topic, $payload);
    })->throws(InvalidArgumentException::class, 'Missing required field: timestamp');

    test('throws exception for missing similarity1 in payload', function () {
        $topic = 'mqtt/face/device001/Rec';
        $payload = json_encode([
            'operator' => 'RecPush',
            'info' => [
                'customId' => 'EMP001',
                'RecordID' => 'REC123456',
                'time' => '2025-10-04 09:15:30',
            ],
        ]);

        AttendanceEventDTO::fromMqttPayload($topic, $payload);
    })->throws(InvalidArgumentException::class, 'Missing required field: similarity1');

    test('parses multiple timestamp formats', function () {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('debug')->atLeast()->once();

        $formats = [
            'Y-m-d H:i:s' => '2025-10-04 09:15:30',
            'Y/m/d H:i:s' => '2025/10/04 09:15:30',
            'ISO 8601' => '2025-10-04T09:15:30',
            'ISO 8601 with Z' => '2025-10-04T09:15:30Z',
            'ISO 8601 with timezone' => '2025-10-04T09:15:30+00:00',
        ];

        foreach ($formats as $format => $timeString) {
            $topic = 'mqtt/face/device001/Rec';
            $payload = json_encode([
                'operator' => 'RecPush',
                'info' => [
                    'customId' => 'EMP001',
                    'RecordID' => 'REC123456',
                    'time' => $timeString,
                    'similarity1' => 95.5,
                ],
            ]);

            $dto = AttendanceEventDTO::fromMqttPayload($topic, $payload);

            expect($dto->timestamp)->toBeInstanceOf(\DateTimeImmutable::class)
                ->and($dto->timestamp->format('H:i:s'))->toBe('09:15:30');
        }
    });

    test('extracts device_id from MQTT topic', function () {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('debug')->atLeast()->once();

        $topic = 'mqtt/face/device123/Rec';
        $payload = json_encode([
            'operator' => 'RecPush',
            'info' => [
                'customId' => 'EMP001',
                'RecordID' => 'REC123456',
                'time' => '2025-10-04 09:15:30',
                'similarity1' => 95.5,
            ],
        ]);

        $dto = AttendanceEventDTO::fromMqttPayload($topic, $payload);

        expect($dto->device_id)->toBe('device123');
    });

    test('throws exception for invalid topic format', function () {
        $topic = 'invalid/topic/format';
        $payload = json_encode([
            'operator' => 'RecPush',
            'info' => [
                'customId' => 'EMP001',
                'RecordID' => 'REC123456',
                'time' => '2025-10-04 09:15:30',
                'similarity1' => 95.5,
            ],
        ]);

        AttendanceEventDTO::fromMqttPayload($topic, $payload);
    })->throws(InvalidArgumentException::class, 'Invalid topic format');

    test('handles missing optional fields gracefully', function () {
        $topic = 'mqtt/face/device001/Rec';
        $payload = json_encode([
            'operator' => 'RecPush',
            'info' => [
                'customId' => 'EMP001',
                'RecordID' => 'REC123456',
                'time' => '2025-10-04 09:15:30',
                'similarity1' => 95.5,
                // All optional fields missing
            ],
        ]);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('debug')->atLeast()->once();

        $dto = AttendanceEventDTO::fromMqttPayload($topic, $payload);

        expect($dto->person_id)->toBeNull()
            ->and($dto->person_name)->toBeNull()
            ->and($dto->device_name)->toBeNull()
            ->and($dto->verify_status)->toBeNull()
            ->and($dto->temperature)->toBeNull()
            ->and($dto->mask_status)->toBeNull()
            ->and($dto->photo_base64)->toBeNull();
    });

    test('converts mask status correctly from isNoMask', function () {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('debug')->atLeast()->once();

        $testCases = [
            ['isNoMask' => '0', 'expected' => 1], // has mask
            ['isNoMask' => '1', 'expected' => 0], // no mask
            ['isNoMask' => 0, 'expected' => 1],   // integer 0 = has mask
            ['isNoMask' => 1, 'expected' => 0],   // integer 1 = no mask
        ];

        foreach ($testCases as $case) {
            $topic = 'mqtt/face/device001/Rec';
            $payload = json_encode([
                'operator' => 'RecPush',
                'info' => [
                    'customId' => 'EMP001',
                    'RecordID' => 'REC123456',
                    'time' => '2025-10-04 09:15:30',
                    'similarity1' => 95.5,
                    'isNoMask' => $case['isNoMask'],
                ],
            ]);

            $dto = AttendanceEventDTO::fromMqttPayload($topic, $payload);

            expect($dto->mask_status)->toBe($case['expected']);
        }
    });

    test('throws exception for invalid JSON payload', function () {
        $topic = 'mqtt/face/device001/Rec';
        $payload = 'invalid json {';

        AttendanceEventDTO::fromMqttPayload($topic, $payload);
    })->throws(InvalidArgumentException::class, 'Invalid JSON payload');
});
