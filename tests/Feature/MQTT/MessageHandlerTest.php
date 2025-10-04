<?php

use App\Services\MQTT\MessageHandler;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

describe('MessageHandler', function () {
    test('can instantiate message handler', function () {
        $handler = new MessageHandler;

        expect($handler)->toBeInstanceOf(MessageHandler::class);
    });

    test('processes recognition event and dispatches to queue', function () {
        $handler = new MessageHandler;

        $topic = 'device/DEVICE001/recognition';
        $payload = json_encode([
            'person_id' => 'EMP001',
            'timestamp' => '2025-10-02 10:30:45',
            'temperature' => 36.5,
            'mask' => true,
            'similarity' => 0.9850,
        ]);

        $handler->handle($topic, $payload);

        Queue::assertPushed(\App\Jobs\ProcessAttendanceEvent::class, function ($job) {
            return $job->event->device_id === 'DEVICE001' &&
                   $job->event->person_id === 'EMP001' &&
                   $job->event->event_type === 'recognition';
        });
    });

    test('processes stranger event and dispatches to queue', function () {
        $handler = new MessageHandler;

        $topic = 'device/DEVICE002/stranger';
        $payload = json_encode([
            'timestamp' => '2025-10-02 11:15:30',
            'temperature' => 37.2,
            'mask' => false,
            'image_url' => '/storage/strangers/20251002_111530.jpg',
        ]);

        $handler->handle($topic, $payload);

        Queue::assertPushed(\App\Jobs\ProcessAttendanceEvent::class, function ($job) {
            return $job->event->device_id === 'DEVICE002' &&
                   $job->event->person_id === null &&
                   $job->event->event_type === 'stranger';
        });
    });

    test('logs received messages', function () {
        $handler = new MessageHandler;

        Log::shouldReceive('channel')
            ->with('mqtt')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->with('MQTT message received', \Mockery::type('array'))
            ->once();

        Log::shouldReceive('info')
            ->with('MQTT message dispatched to queue', \Mockery::type('array'))
            ->once();

        $topic = 'device/DEVICE003/recognition';
        $payload = json_encode([
            'person_id' => 'EMP002',
            'timestamp' => '2025-10-02 12:00:00',
        ]);

        $handler->handle($topic, $payload);
    });

    test('logs errors for invalid payloads', function () {
        $handler = new MessageHandler;

        Log::shouldReceive('channel')
            ->with('mqtt')
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->with('Failed to process MQTT message', \Mockery::type('array'))
            ->once();

        $topic = 'device/DEVICE004/recognition';
        $payload = 'invalid-json{';

        $handler->handle($topic, $payload);

        Queue::assertNothingPushed();
    });

    test('extracts device_id from topic correctly', function () {
        $handler = new MessageHandler;

        $topic = 'device/DEVICE-ABC-123/recognition';
        $payload = json_encode([
            'person_id' => 'EMP003',
            'timestamp' => '2025-10-02 13:00:00',
        ]);

        $handler->handle($topic, $payload);

        Queue::assertPushed(\App\Jobs\ProcessAttendanceEvent::class, function ($job) {
            return $job->event->device_id === 'DEVICE-ABC-123';
        });
    });

    test('dispatches acknowledgement events', function () {
        $handler = new MessageHandler;

        $topic = 'device/DEVICE005/ack';
        $payload = json_encode([
            'status' => 'success',
            'timestamp' => '2025-10-02 14:00:00',
        ]);

        $handler->handle($topic, $payload);

        Queue::assertPushed(\App\Jobs\ProcessAttendanceEvent::class, function ($job) {
            return $job->event->event_type === 'ack' &&
                   $job->event->status === 'success';
        });
    });

    test('can get processing statistics', function () {
        $handler = new MessageHandler;

        $stats = $handler->getStatistics();

        expect($stats)->toBeArray();
        expect($stats)->toHaveKeys(['total_processed', 'total_errors', 'last_processed_at']);
    });

    test('updates statistics after processing messages', function () {
        $handler = new MessageHandler;

        $topic = 'device/DEVICE006/recognition';
        $payload = json_encode([
            'person_id' => 'EMP004',
            'timestamp' => '2025-10-02 15:00:00',
        ]);

        $handler->handle($topic, $payload);

        $stats = $handler->getStatistics();

        expect($stats['total_processed'])->toBe(1);
        expect($stats['total_errors'])->toBe(0);
        expect($stats['last_processed_at'])->not->toBeNull();
    });

    test('increments error count for failed processing', function () {
        $handler = new MessageHandler;

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('error');

        $topic = 'device/DEVICE007/recognition';
        $payload = 'invalid-json';

        $handler->handle($topic, $payload);

        $stats = $handler->getStatistics();

        expect($stats['total_errors'])->toBe(1);
    });
});
