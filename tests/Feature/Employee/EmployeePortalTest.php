<?php

use App\Domain\Attendance\Models\AttendanceViolation;
use App\Domain\Attendance\Models\DailyAttendanceSummary;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Shift;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    // Create user with employee relationship
    $this->user = User::factory()->create();
    $this->employee = Employee::factory()->create([
        'user_id' => $this->user->id,
        'custom_id' => 'EMP001',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $this->shift = Shift::factory()->create([
        'name' => 'Morning Shift',
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
    ]);

    $this->employee->shifts()->attach($this->shift, [
        'effective_from' => Carbon::now()->subDays(30),
    ]);
});

describe('Calendar Endpoint', function () {
    test('employee can view their own calendar data', function () {
        // Create attendance summaries for current month
        $date = Carbon::now()->startOfMonth();

        for ($i = 0; $i < 5; $i++) {
            DailyAttendanceSummary::factory()->create([
                'employee_id' => $this->employee->id,
                'date' => $date->copy()->addDays($i),
                'status' => 'present',
                'total_work_minutes' => 480,
                'is_complete' => true,
            ]);
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/employee/attendance/calendar?month=' . $date->format('Y-m'));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'month',
                    'year',
                    'summaries' => [
                        '*' => [
                            'date',
                            'status',
                            'total_work_minutes',
                            'total_work_hours',
                            'first_check_in',
                            'last_check_out',
                            'is_complete',
                        ],
                    ],
                    'statistics' => [
                        'total_days_present',
                        'total_days_absent',
                        'total_work_hours',
                        'total_overtime_hours',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'month' => $date->month,
                    'year' => $date->year,
                ],
            ]);

        expect($response->json('data.summaries'))->toHaveCount(5);
    });

    test('employee cannot view another employee calendar data', function () {
        $otherUser = User::factory()->create();
        $otherEmployee = Employee::factory()->create(['user_id' => $otherUser->id]);

        DailyAttendanceSummary::factory()->create([
            'employee_id' => $otherEmployee->id,
            'date' => Carbon::now(),
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/employee/attendance/calendar?month=' . Carbon::now()->format('Y-m'));

        $response->assertOk();
        // Should not see other employee's data
        expect($response->json('data.summaries'))->toBeEmpty();
    });

    test('calendar endpoint requires valid month format', function () {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/employee/attendance/calendar?month=invalid');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('month');
    });

    test('calendar defaults to current month when month parameter missing', function () {
        DailyAttendanceSummary::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::now(),
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/employee/attendance/calendar');

        $response->assertOk();
        expect($response->json('data.month'))->toBe(Carbon::now()->month);
        expect($response->json('data.year'))->toBe(Carbon::now()->year);
    });
});

describe('Daily Detail Endpoint', function () {
    test('employee can view their daily detail', function () {
        $date = Carbon::now();

        $summary = DailyAttendanceSummary::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => $date,
            'status' => 'present',
            'total_work_minutes' => 480,
            'total_break_minutes' => 60,
            'overtime_minutes' => 30,
            'first_check_in' => '09:00:00',
            'last_check_out' => '17:30:00',
            'is_complete' => true,
        ]);

        // Create attendance records
        $checkIn = AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'recorded_at' => $date->copy()->setTime(9, 0, 0),
            'direction' => 'check-in',
        ]);

        $checkOut = AttendanceRecord::factory()->create([
            'employee_id' => $this->employee->id,
            'recorded_at' => $date->copy()->setTime(17, 30, 0),
            'direction' => 'check-out',
        ]);

        // Create violations
        $violation = AttendanceViolation::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => $date,
            'type' => 'late_arrival',
            'severity' => 'minor',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/employee/attendance/daily/' . $date->format('Y-m-d'));

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'date',
                        'status',
                        'total_work_minutes',
                        'total_work_hours',
                        'total_break_minutes',
                        'total_break_hours',
                        'overtime_minutes',
                        'overtime_hours',
                        'first_check_in',
                        'last_check_out',
                        'is_complete',
                    ],
                    'records' => [
                        '*' => [
                            'id',
                            'recorded_at',
                            'direction',
                        ],
                    ],
                    'violations' => [
                        '*' => [
                            'id',
                            'type',
                            'severity',
                            'status',
                        ],
                    ],
                ],
            ]);

        expect($response->json('data.records'))->toHaveCount(2);
        expect($response->json('data.violations'))->toHaveCount(1);
    });

    test('employee cannot view another employee daily detail', function () {
        $otherUser = User::factory()->create();
        $otherEmployee = Employee::factory()->create(['user_id' => $otherUser->id]);

        $date = Carbon::now();
        DailyAttendanceSummary::factory()->create([
            'employee_id' => $otherEmployee->id,
            'date' => $date,
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/employee/attendance/daily/' . $date->format('Y-m-d'));

        $response->assertNotFound();
    });

    test('daily detail returns 404 for date with no data', function () {
        $date = Carbon::now()->subYears(10);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/employee/attendance/daily/' . $date->format('Y-m-d'));

        $response->assertNotFound();
    });

    test('daily detail requires valid date format', function () {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/employee/attendance/daily/invalid-date');

        $response->assertUnprocessable();
    });
});

describe('Violations Endpoint', function () {
    test('employee can view their violations', function () {
        $violation1 = AttendanceViolation::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::now(),
            'type' => 'late_arrival',
            'severity' => 'minor',
            'status' => 'pending',
        ]);

        $violation2 = AttendanceViolation::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::now()->subDays(1),
            'type' => 'early_departure',
            'severity' => 'major',
            'status' => 'acknowledged',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/employee/violations');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'date',
                        'type',
                        'severity',
                        'status',
                        'description',
                        'created_at',
                    ],
                ],
                'links',
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                ],
            ]);

        expect($response->json('data'))->toHaveCount(2);
    });

    test('employee can filter violations by date range', function () {
        AttendanceViolation::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::now()->subDays(10),
            'type' => 'late_arrival',
        ]);

        AttendanceViolation::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::now(),
            'type' => 'early_departure',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/employee/violations?from=' . Carbon::now()->subDays(5)->format('Y-m-d') . '&to=' . Carbon::now()->format('Y-m-d'));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    test('employee can filter violations by type', function () {
        AttendanceViolation::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::now(),
            'type' => 'late_arrival',
        ]);

        AttendanceViolation::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::now()->subDays(1),
            'type' => 'early_departure',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/employee/violations?type=late_arrival');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.type'))->toBe('late_arrival');
    });

    test('employee can filter violations by severity', function () {
        AttendanceViolation::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::now(),
            'severity' => 'minor',
        ]);

        AttendanceViolation::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::now()->subDays(1),
            'severity' => 'major',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/employee/violations?severity=major');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.severity'))->toBe('major');
    });

    test('employee can filter violations by status', function () {
        AttendanceViolation::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::now(),
            'status' => 'pending',
        ]);

        AttendanceViolation::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::now()->subDays(1),
            'status' => 'acknowledged',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/employee/violations?status=pending');

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.status'))->toBe('pending');
    });

    test('employee cannot view another employee violations', function () {
        $otherUser = User::factory()->create();
        $otherEmployee = Employee::factory()->create(['user_id' => $otherUser->id]);

        AttendanceViolation::factory()->create([
            'employee_id' => $otherEmployee->id,
            'date' => Carbon::now(),
            'type' => 'late_arrival',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/employee/violations');

        $response->assertOk();
        expect($response->json('data'))->toBeEmpty();
    });
});

describe('Acknowledge Violation Endpoint', function () {
    test('employee can acknowledge their pending violation', function () {
        $violation = AttendanceViolation::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::now(),
            'type' => 'late_arrival',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/employee/violations/' . $violation->id . '/acknowledge');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Violation acknowledged successfully',
            ]);

        $violation->refresh();
        expect($violation->status)->toBe('acknowledged');
        expect($violation->acknowledged_at)->not->toBeNull();
    });

    test('employee cannot acknowledge already acknowledged violation', function () {
        $violation = AttendanceViolation::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::now(),
            'status' => 'acknowledged',
            'acknowledged_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/employee/violations/' . $violation->id . '/acknowledge');

        $response->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'Violation has already been acknowledged or resolved',
            ]);
    });

    test('employee cannot acknowledge another employee violation', function () {
        $otherUser = User::factory()->create();
        $otherEmployee = Employee::factory()->create(['user_id' => $otherUser->id]);

        $violation = AttendanceViolation::factory()->create([
            'employee_id' => $otherEmployee->id,
            'date' => Carbon::now(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/employee/violations/' . $violation->id . '/acknowledge');

        $response->assertNotFound();
    });
});

describe('Dispute Violation Endpoint', function () {
    test('employee can dispute their pending violation with reason', function () {
        $violation = AttendanceViolation::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::now(),
            'type' => 'late_arrival',
            'status' => 'pending',
        ]);

        $reason = 'I was delayed by traffic accident on the highway. Verified by local news.';

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/employee/violations/' . $violation->id . '/dispute', [
                'reason' => $reason,
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Violation disputed successfully. Your manager will review it.',
            ]);

        $violation->refresh();
        expect($violation->status)->toBe('disputed');
        expect($violation->dispute_reason)->toBe($reason);
        expect($violation->disputed_at)->not->toBeNull();
    });

    test('dispute requires reason with minimum 10 characters', function () {
        $violation = AttendanceViolation::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::now(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/employee/violations/' . $violation->id . '/dispute', [
                'reason' => 'short',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    });

    test('employee cannot dispute already resolved violation', function () {
        $violation = AttendanceViolation::factory()->create([
            'employee_id' => $this->employee->id,
            'date' => Carbon::now(),
            'status' => 'resolved',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/employee/violations/' . $violation->id . '/dispute', [
                'reason' => 'I disagree with this violation',
            ]);

        $response->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'Only pending violations can be disputed',
            ]);
    });

    test('employee cannot dispute another employee violation', function () {
        $otherUser = User::factory()->create();
        $otherEmployee = Employee::factory()->create(['user_id' => $otherUser->id]);

        $violation = AttendanceViolation::factory()->create([
            'employee_id' => $otherEmployee->id,
            'date' => Carbon::now(),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/employee/violations/' . $violation->id . '/dispute', [
                'reason' => 'This is not my violation',
            ]);

        $response->assertNotFound();
    });
});

describe('Authorization', function () {
    test('unauthenticated user cannot access employee portal endpoints', function () {
        $this->getJson('/api/v1/employee/attendance/calendar')->assertUnauthorized();
        $this->getJson('/api/v1/employee/attendance/daily/2025-10-06')->assertUnauthorized();
        $this->getJson('/api/v1/employee/violations')->assertUnauthorized();
        $this->postJson('/api/v1/employee/violations/1/acknowledge')->assertUnauthorized();
        $this->postJson('/api/v1/employee/violations/1/dispute')->assertUnauthorized();
    });

    test('user without employee relationship cannot access portal', function () {
        $userWithoutEmployee = User::factory()->create();

        $response = $this->actingAs($userWithoutEmployee)
            ->getJson('/api/v1/employee/attendance/calendar');

        $response->assertForbidden()
            ->assertJsonFragment([
                'message' => 'You do not have an associated employee record',
            ]);
    });
});
