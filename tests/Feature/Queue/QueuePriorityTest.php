<?php

use App\Jobs\ProcessAttendanceEvent;
use App\Jobs\SyncEmployeeToDevices;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    // Clear all queues before each test
    $queues = [
        'attendance-high-priority',
        'attendance-default',
        'notifications',
        'reporting',
    ];

    foreach ($queues as $queue) {
        try {
            $connection = Redis::connection(env('REDIS_QUEUE_CONNECTION', 'queue'));
            $key = 'queues:'.$queue;
            $connection->del($key);
        } catch (\Exception $e) {
            // Queue doesn't exist yet
        }
    }
});

test('high priority jobs are queued to attendance-high-priority', function () {
    Queue::fake();

    $event = new \App\DTOs\AttendanceEventDTO(
        device_id: 'TEST001',
        custom_id: 'EMP001',
        person_id: '12345',
        record_id: 'REC001',
        timestamp: new \DateTimeImmutable(),
        similarity_score: 95.5,
        person_name: 'Test Employee',
        event_type: 'recognition'
    );

    $job = new ProcessAttendanceEvent($event);

    expect($job->queue)->toBe('attendance-high-priority')
        ->and($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(30);
});

test('device sync jobs are queued to attendance-default', function () {
    Queue::fake();

    $job = new SyncEmployeeToDevices(
        employeeId: 1,
        tenantId: 'tenant-123',
        action: 'add'
    );

    expect($job->queue)->toBe(env('QUEUE_DEFAULT', 'attendance-default'))
        ->and($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(60);
});

test('notification jobs are queued to notifications queue', function () {
    Queue::fake();

    $employee = \App\Models\Tenant\Employee::factory()->create();
    $violation = \App\Domain\Attendance\Models\AttendanceViolation::factory()->create([
        'employee_id' => $employee->id,
    ]);

    $notification = new \App\Notifications\ViolationNotification($violation);

    expect($notification->queue)->toBe(env('QUEUE_NOTIFICATIONS', 'notifications'))
        ->and($notification->tries)->toBe(3)
        ->and($notification->timeout)->toBe(30);
});

test('jobs can be pushed to Redis queue', function () {
    Queue::fake();

    $event = new \App\DTOs\AttendanceEventDTO(
        device_id: 'TEST001',
        custom_id: 'EMP001',
        person_id: '12345',
        record_id: 'REC001',
        timestamp: new \DateTimeImmutable(),
        similarity_score: 95.5,
        person_name: 'Test Employee',
        event_type: 'recognition'
    );

    ProcessAttendanceEvent::dispatch($event);

    // Verify job was dispatched to correct queue
    Queue::assertPushed(ProcessAttendanceEvent::class, function ($job) {
        return $job->queue === 'attendance-high-priority';
    });
});

test('queue monitor command runs successfully', function () {
    $this->artisan('queue:monitor')
        ->assertSuccessful();
});

test('queue metrics API returns correct structure', function () {
    $user = \App\Models\User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/queue/metrics');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'metrics' => [
                '*' => [
                    'queue',
                    'size',
                    'failed_jobs',
                    'status',
                ],
            ],
            'timestamp',
            'total_jobs',
            'total_failed',
        ]);

    expect($response->json('metrics'))->toHaveCount(4);
});

test('queue metrics API returns specific queue info', function () {
    $user = \App\Models\User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/queue/metrics/attendance-high-priority');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'queue',
            'size',
            'failed_jobs',
            'status',
            'timestamp',
        ])
        ->assertJson([
            'queue' => 'attendance-high-priority',
        ]);
});

test('queue status is healthy when size is below warning threshold', function () {
    $controller = new \App\Http\Controllers\Api\V1\QueueMetricsController();
    $reflection = new \ReflectionClass($controller);
    $method = $reflection->getMethod('getQueueHealth');
    $method->setAccessible(true);

    // Mock small queue size
    $status = $method->invoke($controller, 'attendance-high-priority');

    expect($status)->toBe('healthy');
});

test('Redis queue connection is properly configured', function () {
    $connection = Redis::connection('queue');

    expect($connection)->not->toBeNull();

    // Test connection works
    $result = $connection->ping();
    expect($result)->toBeTrue();
});

test('all queue names are properly configured in environment', function () {
    expect(env('QUEUE_HIGH_PRIORITY'))->toBe('attendance-high-priority')
        ->and(env('QUEUE_DEFAULT'))->toBe('attendance-default')
        ->and(env('QUEUE_NOTIFICATIONS'))->toBe('notifications')
        ->and(env('QUEUE_REPORTING'))->toBe('reporting');
});
