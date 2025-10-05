<?php

use App\Domain\Attendance\Models\DailyAttendanceSummary;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create authenticated user for API requests
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

describe('Attendance Summary API - Index Endpoint', function () {
    test('returns paginated list of attendance summaries', function () {
        $employee = Employee::factory()->create();

        // Create 5 summaries
        DailyAttendanceSummary::factory()->count(5)->create([
            'employee_id' => $employee->id,
        ]);

        $response = $this->getJson('/api/v1/attendance-summaries');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'employee_id',
                        'date',
                        'first_check_in',
                        'last_check_out',
                        'total_work_minutes',
                        'total_work_hours',
                        'total_break_minutes',
                        'total_break_hours',
                        'overtime_minutes',
                        'overtime_hours',
                        'status',
                        'is_complete',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'links',
                'meta',
            ])
            ->assertJsonCount(5, 'data');
    });

    test('filters summaries by employee_id', function () {
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        DailyAttendanceSummary::factory()->count(3)->create(['employee_id' => $employee1->id]);
        DailyAttendanceSummary::factory()->count(2)->create(['employee_id' => $employee2->id]);

        $response = $this->getJson("/api/v1/attendance-summaries?employee_id={$employee1->id}");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');

        // Verify all returned summaries belong to employee1
        $data = $response->json('data');
        foreach ($data as $summary) {
            expect($summary['employee_id'])->toBe($employee1->id);
        }
    });

    test('filters summaries by specific date', function () {
        $employee = Employee::factory()->create();
        $targetDate = Carbon::parse('2025-10-05');

        DailyAttendanceSummary::factory()->create([
            'employee_id' => $employee->id,
            'date' => $targetDate,
        ]);

        DailyAttendanceSummary::factory()->create([
            'employee_id' => $employee->id,
            'date' => $targetDate->copy()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/attendance-summaries?date=2025-10-05');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2025-10-05');
    });

    test('filters summaries by date range (from/to)', function () {
        $employee = Employee::factory()->create();

        // Create summaries for Oct 1-10
        for ($i = 1; $i <= 10; $i++) {
            DailyAttendanceSummary::factory()->create([
                'employee_id' => $employee->id,
                'date' => Carbon::parse("2025-10-{$i}"),
            ]);
        }

        $response = $this->getJson('/api/v1/attendance-summaries?from=2025-10-03&to=2025-10-07');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data');

        $dates = collect($response->json('data'))->pluck('date');
        expect($dates)->toContain('2025-10-03', '2025-10-04', '2025-10-05', '2025-10-06', '2025-10-07');
    });

    test('filters summaries by status', function () {
        $employee = Employee::factory()->create();

        DailyAttendanceSummary::factory()->count(3)->create([
            'employee_id' => $employee->id,
            'status' => 'present',
        ]);

        DailyAttendanceSummary::factory()->count(2)->create([
            'employee_id' => $employee->id,
            'status' => 'absent',
        ]);

        $response = $this->getJson('/api/v1/attendance-summaries?status=present');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');

        $data = $response->json('data');
        foreach ($data as $summary) {
            expect($summary['status'])->toBe('present');
        }
    });

    test('combines multiple filters', function () {
        $employee1 = Employee::factory()->create();
        $employee2 = Employee::factory()->create();

        // Employee 1: 3 present, 2 absent in Oct 1-5
        for ($i = 1; $i <= 3; $i++) {
            DailyAttendanceSummary::factory()->create([
                'employee_id' => $employee1->id,
                'date' => Carbon::parse("2025-10-{$i}"),
                'status' => 'present',
            ]);
        }

        for ($i = 4; $i <= 5; $i++) {
            DailyAttendanceSummary::factory()->create([
                'employee_id' => $employee1->id,
                'date' => Carbon::parse("2025-10-{$i}"),
                'status' => 'absent',
            ]);
        }

        // Employee 2: some summaries (should be filtered out)
        DailyAttendanceSummary::factory()->count(3)->create([
            'employee_id' => $employee2->id,
            'status' => 'present',
        ]);

        $response = $this->getJson("/api/v1/attendance-summaries?employee_id={$employee1->id}&status=present&from=2025-10-01&to=2025-10-05");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    });

    test('respects pagination parameters', function () {
        $employee = Employee::factory()->create();

        DailyAttendanceSummary::factory()->count(25)->create([
            'employee_id' => $employee->id,
        ]);

        $response = $this->getJson('/api/v1/attendance-summaries?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 25);
    });

    test('includes employee relationship when requested', function () {
        $employee = Employee::factory()->create(['name' => 'John Doe']);

        DailyAttendanceSummary::factory()->create([
            'employee_id' => $employee->id,
        ]);

        $response = $this->getJson('/api/v1/attendance-summaries?include=employee');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.employee.name', 'John Doe');
    });
});

describe('Attendance Summary API - Show Endpoint', function () {
    test('returns single attendance summary by ID', function () {
        $employee = Employee::factory()->create();
        $summary = DailyAttendanceSummary::factory()->create([
            'employee_id' => $employee->id,
            'date' => Carbon::parse('2025-10-05'),
            'total_work_minutes' => 480,
        ]);

        $response = $this->getJson("/api/v1/attendance-summaries/{$summary->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $summary->id)
            ->assertJsonPath('data.employee_id', $employee->id)
            ->assertJsonPath('data.date', '2025-10-05')
            ->assertJsonPath('data.total_work_minutes', 480);

        // Check total_work_hours (as integer from DB, will be converted in accessor)
        $hours = $response->json('data.total_work_hours');
        expect($hours)->toBeGreaterThanOrEqual(7.99)->and($hours)->toBeLessThanOrEqual(8.01);
    });

    test('returns 404 for non-existent summary', function () {
        $response = $this->getJson('/api/v1/attendance-summaries/99999');

        $response->assertStatus(404);
    });

    test('includes employee relationship when requested', function () {
        $employee = Employee::factory()->create(['name' => 'Jane Smith']);
        $summary = DailyAttendanceSummary::factory()->create([
            'employee_id' => $employee->id,
        ]);

        $response = $this->getJson("/api/v1/attendance-summaries/{$summary->id}?include=employee");

        $response->assertStatus(200)
            ->assertJsonPath('data.employee.name', 'Jane Smith');
    });
});

describe('Attendance Summary API - Recalculate Endpoint', function () {
    test('recalculates summaries for employee and date range', function () {
        $employee = Employee::factory()->create();

        // Create attendance records for 3 days
        for ($i = 1; $i <= 3; $i++) {
            $date = Carbon::parse("2025-10-0{$i}");

            AttendanceRecord::factory()->create([
                'employee_id' => $employee->id,
                'recorded_at' => $date->copy()->setTimeFromTimeString('09:00:00'),
                'direction' => 'check-in',
            ]);

            AttendanceRecord::factory()->create([
                'employee_id' => $employee->id,
                'recorded_at' => $date->copy()->setTimeFromTimeString('17:00:00'),
                'direction' => 'check-out',
            ]);
        }

        $response = $this->postJson('/api/v1/attendance-summaries/recalculate', [
            'employee_id' => $employee->id,
            'from' => '2025-10-01',
            'to' => '2025-10-03',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'employee_id',
                    'from',
                    'to',
                    'summaries_recalculated',
                ],
            ])
            ->assertJsonPath('data.summaries_recalculated', 3);

        // Verify summaries were created
        expect(DailyAttendanceSummary::where('employee_id', $employee->id)->count())->toBe(3);
    });

    test('validates required fields for recalculation', function () {
        $response = $this->postJson('/api/v1/attendance-summaries/recalculate', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['employee_id', 'from', 'to']);
    });

    test('validates date formats', function () {
        $employee = Employee::factory()->create();

        $response = $this->postJson('/api/v1/attendance-summaries/recalculate', [
            'employee_id' => $employee->id,
            'from' => 'invalid-date',
            'to' => '2025-10-31',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['from']);
    });

    test('validates employee exists', function () {
        $response = $this->postJson('/api/v1/attendance-summaries/recalculate', [
            'employee_id' => 99999,
            'from' => '2025-10-01',
            'to' => '2025-10-31',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['employee_id']);
    });

    test('validates date range order', function () {
        $employee = Employee::factory()->create();

        $response = $this->postJson('/api/v1/attendance-summaries/recalculate', [
            'employee_id' => $employee->id,
            'from' => '2025-10-31',
            'to' => '2025-10-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to']);
    });

    test('updates existing summaries during recalculation', function () {
        $employee = Employee::factory()->create();

        // Create incorrect initial summary
        DailyAttendanceSummary::factory()->create([
            'employee_id' => $employee->id,
            'date' => Carbon::parse('2025-10-01'),
            'total_work_minutes' => 100,
            'status' => 'half-day',
        ]);

        // Create correct attendance records
        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => Carbon::parse('2025-10-01 09:00:00'),
            'direction' => 'check-in',
        ]);

        AttendanceRecord::factory()->create([
            'employee_id' => $employee->id,
            'recorded_at' => Carbon::parse('2025-10-01 17:00:00'),
            'direction' => 'check-out',
        ]);

        $response = $this->postJson('/api/v1/attendance-summaries/recalculate', [
            'employee_id' => $employee->id,
            'from' => '2025-10-01',
            'to' => '2025-10-01',
        ]);

        $response->assertStatus(200);

        // Verify summary was updated (not duplicated)
        expect(DailyAttendanceSummary::count())->toBe(1);

        $summary = DailyAttendanceSummary::first();
        expect($summary->total_work_minutes)->toBe(480)
            ->and($summary->status)->toBe('present');
    });
});

// Note: Authentication is enforced by the 'auth:sanctum' middleware in routes/api.php
// Manual testing with curl or API client should verify 401 responses for unauthenticated requests
