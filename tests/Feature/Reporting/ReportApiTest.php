<?php

use App\Domain\Attendance\Models\AttendanceViolation;
use App\Domain\Attendance\Models\DailyAttendanceSummary;
use App\Models\Tenant\Employee;
use App\Models\User;

test('attendance report API returns JSON data', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create();

    DailyAttendanceSummary::factory()->create([
        'employee_id' => $employee->id,
        'date' => '2025-10-01',
        'status' => 'present',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/reports/attendance', [
            'from_date' => '2025-10-01',
            'to_date' => '2025-10-31',
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'period',
                'filters',
                'summary',
                'records',
            ],
        ])
        ->assertJson(['success' => true]);
});

test('attendance report API validates required fields', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/reports/attendance', []);

    $response->assertStatus(422)
        ->assertJsonStructure([
            'success',
            'errors',
        ]);
});

test('attendance report API validates date order', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/reports/attendance', [
            'from_date' => '2025-10-31',
            'to_date' => '2025-10-01',
        ]);

    $response->assertStatus(422);
});

test('attendance report API generates PDF file', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create();

    DailyAttendanceSummary::factory()->create([
        'employee_id' => $employee->id,
        'date' => '2025-10-01',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/reports/attendance', [
            'from_date' => '2025-10-01',
            'to_date' => '2025-10-31',
            'format' => 'pdf',
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'file',
            'download_url',
        ]);

    // Verify file was created
    $filename = $response->json('file');
    expect(file_exists(storage_path("app/reports/{$filename}")))->toBeTrue();

    // Cleanup
    @unlink(storage_path("app/reports/{$filename}"));
});

test('attendance report API generates Excel file', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create();

    DailyAttendanceSummary::factory()->create([
        'employee_id' => $employee->id,
        'date' => '2025-10-01',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/reports/attendance', [
            'from_date' => '2025-10-01',
            'to_date' => '2025-10-31',
            'format' => 'excel',
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'file',
        ]);

    // Cleanup
    $filename = $response->json('file');
    @unlink(storage_path("app/reports/{$filename}"));
});

test('violation report API returns JSON data', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create();

    AttendanceViolation::factory()->create([
        'employee_id' => $employee->id,
        'violation_date' => '2025-10-01',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/reports/violations', [
            'from_date' => '2025-10-01',
            'to_date' => '2025-10-31',
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'data' => [
                'period',
                'filters',
                'summary' => [
                    'total_violations',
                    'employees_with_violations',
                    'type_breakdown',
                    'severity_breakdown',
                    'repeat_offenders',
                ],
                'records',
            ],
        ]);
});

test('violation report API filters by type', function () {
    $user = User::factory()->create();
    $employee = Employee::factory()->create();

    AttendanceViolation::factory()->create([
        'employee_id' => $employee->id,
        'violation_date' => '2025-10-01',
        'type' => 'late_arrival',
    ]);
    AttendanceViolation::factory()->create([
        'employee_id' => $employee->id,
        'violation_date' => '2025-10-02',
        'type' => 'early_departure',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/reports/violations', [
            'from_date' => '2025-10-01',
            'to_date' => '2025-10-31',
            'type' => 'late_arrival',
        ]);

    $response->assertStatus(200);
    expect($response->json('data.records'))->toHaveCount(1);
});

test('report download endpoint validates file parameter', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/reports/download');

    $response->assertStatus(422);
});

test('report download endpoint returns 404 for non-existent file', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/reports/download?file=nonexistent.pdf');

    $response->assertStatus(404);
});

test('unauthenticated requests are rejected', function () {
    $response = $this->postJson('/api/v1/reports/attendance', [
        'from_date' => '2025-10-01',
        'to_date' => '2025-10-31',
    ]);

    $response->assertStatus(401);
});
