<?php

use App\Jobs\ProcessStrangerEvent;
use App\Services\MQTT\MessageHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

describe('MessageHandler - Stranger Event Routing', function () {
    test('routes stranger event to ProcessStrangerEvent job', function () {
        Queue::fake();

        $handler = new MessageHandler;

        $topic = 'mqtt/face/DEVICE001/Stranger';
        $payload = json_encode([
            'operator' => 'StrangerPush',
            'info' => [
                'RecordID' => 'REC123456',
                'time' => '2025-10-07 15:30:45',
                'similarity1' => 45.5,
                'pic' => base64_encode('fake-image-data'),
                'personName' => 'Unknown',
                'facesluiceName' => 'Main Entrance',
                'temperature' => 36.5,
                'mask_status' => 1,
            ],
        ]);

        $handler->handle($topic, $payload);

        Queue::assertPushed(ProcessStrangerEvent::class, function ($job) {
            return $job->event->device_id === 'DEVICE001'
                && $job->event->record_id === 'REC123456'
                && $job->event->similarity_score === 45.5;
        });
    });

    test('handles stranger event with minimal data', function () {
        Queue::fake();

        $handler = new MessageHandler;

        $topic = 'mqtt/face/DEVICE002/Stranger';
        $payload = json_encode([
            'operator' => 'StrangerPush',
            'info' => [
                'RecordID' => 'REC789',
                'time' => '2025-10-07 16:00:00',
                'similarity1' => 30.0,
                'pic' => base64_encode('another-fake-image'),
            ],
        ]);

        $handler->handle($topic, $payload);

        Queue::assertPushed(ProcessStrangerEvent::class);
    });

    test('increments processing statistics on stranger event', function () {
        Queue::fake();

        $handler = new MessageHandler;

        $topic = 'mqtt/face/DEVICE001/Stranger';
        $payload = json_encode([
            'operator' => 'StrangerPush',
            'info' => [
                'RecordID' => 'REC123',
                'time' => '2025-10-07 15:30:45',
                'similarity1' => 45.5,
                'pic' => base64_encode('fake-image-data'),
            ],
        ]);

        $handler->handle($topic, $payload);

        $stats = $handler->getStatistics();

        expect($stats['total_processed'])->toBe(1)
            ->and($stats['total_errors'])->toBe(0);
    });

    test('handles invalid stranger event payload gracefully', function () {
        Queue::fake();

        $handler = new MessageHandler;

        $topic = 'mqtt/face/DEVICE001/Stranger';
        $payload = 'invalid-json';

        $handler->handle($topic, $payload);

        $stats = $handler->getStatistics();

        expect($stats['total_errors'])->toBe(1);
        Queue::assertNothingPushed();
    });

    test('handles stranger event with missing photo', function () {
        Queue::fake();

        $handler = new MessageHandler;

        $topic = 'mqtt/face/DEVICE001/Stranger';
        $payload = json_encode([
            'operator' => 'StrangerPush',
            'info' => [
                'RecordID' => 'REC123',
                'time' => '2025-10-07 15:30:45',
                'similarity1' => 45.5,
                // Missing 'pic' field
            ],
        ]);

        $handler->handle($topic, $payload);

        $stats = $handler->getStatistics();

        expect($stats['total_errors'])->toBe(1);
        Queue::assertNothingPushed();
    });
});
