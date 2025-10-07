<?php

use App\Models\Tenant\Department;
use App\Models\Tenant\Device;
use App\Models\Tenant\Employee;
use App\Models\Tenant\StrangerLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Fake S3 storage
    Storage::fake('s3');

    // Create authenticated user
    $this->user = User::factory()->create([
        'email' => 'admin@example.com',
    ]);

    // Create device for stranger logs
    $this->device = Device::factory()->create([
        'device_id' => 'DEVICE001',
        'name' => 'Main Entrance',
    ]);
});

describe('StrangerLogController - Index', function () {
    test('lists stranger logs with pagination', function () {
        // Create stranger logs
        StrangerLog::factory()->count(20)->create([
            'device_id' => $this->device->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/stranger-logs');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'device_id', 'detected_at', 'photo_path', 'match_status'],
                ],
                'links',
                'meta' => ['current_page', 'total', 'per_page'],
            ])
            ->assertJsonCount(15, 'data'); // Default pagination
    });

    test('filters stranger logs by match_status', function () {
        StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'match_status' => 'unreviewed',
        ]);

        StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'match_status' => 'matched',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/stranger-logs?match_status=unreviewed');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.match_status', 'unreviewed');
    });

    test('filters stranger logs by device_id', function () {
        $device2 = Device::factory()->create(['device_id' => 'DEVICE002']);

        StrangerLog::factory()->create(['device_id' => $this->device->id]);
        StrangerLog::factory()->create(['device_id' => $device2->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/stranger-logs?device_id='.$this->device->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.device_id', $this->device->id);
    });

    test('filters stranger logs by date range', function () {
        StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'detected_at' => '2025-10-01 10:00:00',
        ]);

        StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'detected_at' => '2025-10-15 10:00:00',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/stranger-logs?from=2025-10-01&to=2025-10-10');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    test('includes relationships when requested', function () {
        StrangerLog::factory()->create([
            'device_id' => $this->device->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/stranger-logs?include=device,employee');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'device'],
                ],
            ]);
    });
});

describe('StrangerLogController - Show', function () {
    test('shows single stranger log', function () {
        $log = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/stranger-logs/'.$log->id);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'device_id', 'detected_at', 'photo_path', 'match_status']])
            ->assertJsonPath('data.id', $log->id);
    });

    test('returns 404 for non-existent stranger log', function () {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/stranger-logs/99999');

        $response->assertNotFound();
    });
});

describe('StrangerLogController - Match', function () {
    test('matches stranger log to employee', function () {
        $department = Department::factory()->create();
        $employee = Employee::factory()->create([
            'department_id' => $department->id,
        ]);

        $log = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'match_status' => 'unreviewed',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/stranger-logs/'.$log->id.'/match', [
                'employee_id' => $employee->id,
                'notes' => 'Confirmed match with security team',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.match_status', 'matched')
            ->assertJsonPath('data.employee_id', $employee->id);

        $this->assertDatabaseHas('stranger_logs', [
            'id' => $log->id,
            'match_status' => 'matched',
            'employee_id' => $employee->id,
            'matched_by' => $this->user->id,
        ]);
    });

    test('validates employee_id when matching', function () {
        $log = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/stranger-logs/'.$log->id.'/match', [
                'employee_id' => 99999, // Non-existent employee
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);
    });

    test('cannot match already matched stranger log', function () {
        $department = Department::factory()->create();
        $employee = Employee::factory()->create(['department_id' => $department->id]);

        $log = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'match_status' => 'matched',
            'employee_id' => $employee->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/stranger-logs/'.$log->id.'/match', [
                'employee_id' => $employee->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Stranger log is already matched']);
    });
});

describe('StrangerLogController - Mark Security Issue', function () {
    test('marks stranger log as security issue', function () {
        $log = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'match_status' => 'unreviewed',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/stranger-logs/'.$log->id.'/mark-security-issue', [
                'notes' => 'Suspicious behavior - attempted tailgating',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.match_status', 'security_issue')
            ->assertJsonPath('data.notes', 'Suspicious behavior - attempted tailgating');

        $this->assertDatabaseHas('stranger_logs', [
            'id' => $log->id,
            'match_status' => 'security_issue',
            'notes' => 'Suspicious behavior - attempted tailgating',
        ]);
    });

    test('requires notes when marking as security issue', function () {
        $log = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/stranger-logs/'.$log->id.'/mark-security-issue', [
                'notes' => '', // Empty notes
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['notes']);
    });
});

describe('StrangerLogController - Bulk Processing', function () {
    test('bulk processes stranger logs with match action', function () {
        $department = Department::factory()->create();
        $employee1 = Employee::factory()->create(['department_id' => $department->id]);
        $employee2 = Employee::factory()->create(['department_id' => $department->id]);

        $log1 = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'match_status' => 'unreviewed',
        ]);

        $log2 = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'match_status' => 'unreviewed',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/stranger-logs/bulk-process', [
                'action' => 'match',
                'stranger_log_ids' => [$log1->id, $log2->id],
                'employee_id' => $employee1->id,
                'notes' => 'Bulk matched by security team',
            ]);

        $response->assertAccepted()
            ->assertJsonStructure(['message', 'job_id']);
    });

    test('bulk processes stranger logs with mark-security-issue action', function () {
        $log1 = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'match_status' => 'unreviewed',
        ]);

        $log2 = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'match_status' => 'unreviewed',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/stranger-logs/bulk-process', [
                'action' => 'mark-security-issue',
                'stranger_log_ids' => [$log1->id, $log2->id],
                'notes' => 'Suspicious behavior detected',
            ]);

        $response->assertAccepted()
            ->assertJsonStructure(['message', 'job_id']);
    });

    test('bulk processes stranger logs with delete action', function () {
        $log1 = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'match_status' => 'unreviewed',
        ]);

        $log2 = StrangerLog::factory()->create([
            'device_id' => $this->device->id,
            'match_status' => 'unreviewed',
        ]);

        // Create fake photos in S3
        Storage::disk('s3')->put($log1->photo_path, 'fake-photo-content');
        Storage::disk('s3')->put($log2->photo_path, 'fake-photo-content');

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/stranger-logs/bulk-process', [
                'action' => 'delete',
                'stranger_log_ids' => [$log1->id, $log2->id],
            ]);

        $response->assertAccepted()
            ->assertJsonStructure(['message', 'job_id']);
    });

    test('validates action field for bulk processing', function () {
        $log = StrangerLog::factory()->create(['device_id' => $this->device->id]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/stranger-logs/bulk-process', [
                'action' => 'invalid-action',
                'stranger_log_ids' => [$log->id],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['action']);
    });

    test('validates stranger_log_ids are required and array', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/stranger-logs/bulk-process', [
                'action' => 'delete',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['stranger_log_ids']);
    });

    test('validates employee_id is required for match action', function () {
        $log = StrangerLog::factory()->create(['device_id' => $this->device->id]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/stranger-logs/bulk-process', [
                'action' => 'match',
                'stranger_log_ids' => [$log->id],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);
    });

    test('validates notes are required for mark-security-issue action', function () {
        $log = StrangerLog::factory()->create(['device_id' => $this->device->id]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/stranger-logs/bulk-process', [
                'action' => 'mark-security-issue',
                'stranger_log_ids' => [$log->id],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['notes']);
    });

    test('validates maximum 100 logs can be processed at once', function () {
        $logs = StrangerLog::factory()->count(101)->create([
            'device_id' => $this->device->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/stranger-logs/bulk-process', [
                'action' => 'delete',
                'stranger_log_ids' => $logs->pluck('id')->toArray(),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['stranger_log_ids']);
    });
});

describe('StrangerLogController - Authorization', function () {
    test('requires authentication for all endpoints', function () {
        $log = StrangerLog::factory()->create(['device_id' => $this->device->id]);

        // Index
        $this->getJson('/api/v1/stranger-logs')->assertUnauthorized();

        // Show
        $this->getJson('/api/v1/stranger-logs/'.$log->id)->assertUnauthorized();

        // Match
        $this->postJson('/api/v1/stranger-logs/'.$log->id.'/match')->assertUnauthorized();

        // Mark security issue
        $this->postJson('/api/v1/stranger-logs/'.$log->id.'/mark-security-issue')->assertUnauthorized();

        // Bulk process
        $this->postJson('/api/v1/stranger-logs/bulk-process')->assertUnauthorized();
    });
});
