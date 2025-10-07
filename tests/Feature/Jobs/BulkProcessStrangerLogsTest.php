<?php

use App\Jobs\BulkProcessStrangerLogs;
use App\Models\Tenant\Department;
use App\Models\Tenant\Device;
use App\Models\Tenant\Employee;
use App\Models\Tenant\StrangerLog;
use App\Models\User;
use App\Services\PhotoStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Fake S3 storage
    Storage::fake('s3');

    // Create authenticated user
    $this->user = User::factory()->create();

    // Create device for stranger logs
    $this->device = Device::factory()->create(['device_id' => 'DEVICE001']);
});

describe('BulkProcessStrangerLogs Job', function () {
    test('job is configured with correct retry settings', function () {
        $job = new BulkProcessStrangerLogs(
            'match',
            [1, 2],
            1,
            1,
            'notes',
            'job-id-123'
        );

        expect($job->tries)->toBe(3)
            ->and($job->backoff())->toBe([60, 300, 900]);
    });

    test('processes bulk match action successfully', function () {
        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        $log1 = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'match_status' => 'unreviewed',
        ]);

        $log2 = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'match_status' => 'unreviewed',
        ]);

        $job = new BulkProcessStrangerLogs(
            'match',
            [$log1->id, $log2->id],
            $this->user->id,
            $employee->id,
            'Bulk matched by security team',
            'job-id-123'
        );

        $job->handle(app(PhotoStorageService::class));

        // Verify both logs are matched
        $this->assertDatabaseHas('stranger_logs', [
            'id' => $log1->id,
            'match_status' => 'matched',
            'employee_id' => $employee->id,
            'matched_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('stranger_logs', [
            'id' => $log2->id,
            'match_status' => 'matched',
            'employee_id' => $employee->id,
            'matched_by' => $this->user->id,
        ]);
    });

    test('processes bulk mark-security-issue action successfully', function () {
        $log1 = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'match_status' => 'unreviewed',
        ]);

        $log2 = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'match_status' => 'unreviewed',
        ]);

        $job = new BulkProcessStrangerLogs(
            'mark-security-issue',
            [$log1->id, $log2->id],
            $this->user->id,
            null,
            'Suspicious behavior detected',
            'job-id-123'
        );

        $job->handle(app(PhotoStorageService::class));

        // Verify both logs are marked as security issues
        $this->assertDatabaseHas('stranger_logs', [
            'id' => $log1->id,
            'match_status' => 'security_issue',
            'notes' => 'Suspicious behavior detected',
        ]);

        $this->assertDatabaseHas('stranger_logs', [
            'id' => $log2->id,
            'match_status' => 'security_issue',
            'notes' => 'Suspicious behavior detected',
        ]);
    });

    test('processes bulk delete action with photo cleanup', function () {
        $log1 = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'photo_path' => 'strangers/photos/photo1.jpg',
        ]);

        $log2 = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'photo_path' => 'strangers/photos/photo2.jpg',
        ]);

        // Create fake photos in S3
        Storage::disk('s3')->put($log1->photo_path, 'fake-photo-content-1');
        Storage::disk('s3')->put($log2->photo_path, 'fake-photo-content-2');

        $job = new BulkProcessStrangerLogs(
            'delete',
            [$log1->id, $log2->id],
            $this->user->id,
            null,
            null,
            'job-id-123'
        );

        $job->handle(app(PhotoStorageService::class));

        // Verify logs are deleted from database
        $this->assertDatabaseMissing('stranger_logs', ['id' => $log1->id]);
        $this->assertDatabaseMissing('stranger_logs', ['id' => $log2->id]);

        // Verify photos are deleted from S3
        Storage::disk('s3')->assertMissing($log1->photo_path);
        Storage::disk('s3')->assertMissing($log2->photo_path);
    });

    test('skips already matched logs in match action', function () {
        $department = Department::factory()->create();
        $employee1 = Employee::factory()->create(['department_id' => $department->id]);
        $employee2 = Employee::factory()->create(['department_id' => $department->id]);

        $log1 = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'match_status' => 'matched',
            'employee_id' => $employee1->id,
        ]);

        $log2 = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'match_status' => 'unreviewed',
        ]);

        $job = new BulkProcessStrangerLogs(
            'match',
            [$log1->id, $log2->id],
            $this->user->id,
            $employee2->id,
            'Bulk match attempt',
            'job-id-123'
        );

        $job->handle(app(PhotoStorageService::class));

        // Verify log1 is not changed (already matched)
        $this->assertDatabaseHas('stranger_logs', [
            'id' => $log1->id,
            'employee_id' => $employee1->id, // Still employee1
        ]);

        // Verify log2 is matched to employee2
        $this->assertDatabaseHas('stranger_logs', [
            'id' => $log2->id,
            'match_status' => 'matched',
            'employee_id' => $employee2->id,
        ]);
    });

    test('handles large batch processing efficiently', function () {
        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        // Create 50 stranger logs
        $logs = StrangerLog::factory()->count(50)->create([
            'device_id' => $this->device->id,
            'match_status' => 'unreviewed',
        ]);

        $job = new BulkProcessStrangerLogs(
            'match',
            $logs->pluck('id')->toArray(),
            $this->user->id,
            $employee->id,
            'Bulk matched - large batch',
            'job-id-123'
        );

        $startTime = microtime(true);
        $job->handle(app(PhotoStorageService::class));
        $executionTime = microtime(true) - $startTime;

        // Verify all logs are matched
        foreach ($logs as $log) {
            $this->assertDatabaseHas('stranger_logs', [
                'id' => $log->id,
                'match_status' => 'matched',
                'employee_id' => $employee->id,
            ]);
        }

        // Performance assertion - should complete in under 2 seconds
        expect($executionTime)->toBeLessThan(2);
    });
});
