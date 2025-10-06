<?php

use App\Domain\Attendance\Models\AttendanceViolation;
use App\Models\Tenant\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create authenticated user
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);

    // Create test employees
    $this->employee1 = Employee::factory()->create();
    $this->employee2 = Employee::factory()->create();
});

test('can list violations', function () {
    // Create violations for different employees
    AttendanceViolation::factory()->count(5)->create([
        'employee_id' => $this->employee1->id,
    ]);

    AttendanceViolation::factory()->count(3)->create([
        'employee_id' => $this->employee2->id,
    ]);

    $response = $this->getJson('/api/v1/violations');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'employee_id',
                    'employee_name',
                    'violation_date',
                    'type',
                    'severity',
                    'minutes_deviation',
                    'status',
                    'created_at',
                    'updated_at',
                ],
            ],
            'links',
            'meta',
        ]);

    expect($response->json('data'))->toHaveCount(8);
});

test('can filter violations by employee', function () {
    AttendanceViolation::factory()->count(3)->create([
        'employee_id' => $this->employee1->id,
    ]);

    AttendanceViolation::factory()->count(2)->create([
        'employee_id' => $this->employee2->id,
    ]);

    $response = $this->getJson('/api/v1/violations?employee_id=' . $this->employee1->id);

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(3);

    foreach ($response->json('data') as $violation) {
        expect($violation['employee_id'])->toBe($this->employee1->id);
    }
});

test('can filter violations by type', function () {
    AttendanceViolation::factory()->lateArrival()->count(2)->create([
        'employee_id' => $this->employee1->id,
    ]);

    AttendanceViolation::factory()->earlyDeparture()->count(1)->create([
        'employee_id' => $this->employee1->id,
    ]);

    $response = $this->getJson('/api/v1/violations?type=late_arrival');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);

    foreach ($response->json('data') as $violation) {
        expect($violation['type'])->toBe('late_arrival');
    }
});

test('can filter violations by severity', function () {
    AttendanceViolation::factory()->minor()->count(2)->create([
        'employee_id' => $this->employee1->id,
    ]);

    AttendanceViolation::factory()->major()->count(1)->create([
        'employee_id' => $this->employee1->id,
    ]);

    $response = $this->getJson('/api/v1/violations?severity=minor');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);

    foreach ($response->json('data') as $violation) {
        expect($violation['severity'])->toBe('minor');
    }
});

test('can filter violations by date range', function () {
    AttendanceViolation::factory()->create([
        'employee_id' => $this->employee1->id,
        'violation_date' => '2025-10-01',
    ]);

    AttendanceViolation::factory()->create([
        'employee_id' => $this->employee1->id,
        'violation_date' => '2025-10-15',
    ]);

    AttendanceViolation::factory()->create([
        'employee_id' => $this->employee1->id,
        'violation_date' => '2025-10-30',
    ]);

    $response = $this->getJson('/api/v1/violations?date_from=2025-10-10&date_to=2025-10-20');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.violation_date'))->toBe('2025-10-15');
});

test('can get single violation', function () {
    $violation = AttendanceViolation::factory()->create([
        'employee_id' => $this->employee1->id,
    ]);

    $response = $this->getJson('/api/v1/violations/' . $violation->id);

    $response->assertStatus(200)
        ->assertJson([
            'data' => [
                'id' => $violation->id,
                'employee_id' => $this->employee1->id,
                'type' => $violation->type,
                'severity' => $violation->severity,
            ],
        ]);
});

test('can get violations for specific employee', function () {
    AttendanceViolation::factory()->count(3)->create([
        'employee_id' => $this->employee1->id,
    ]);

    AttendanceViolation::factory()->count(2)->create([
        'employee_id' => $this->employee2->id,
    ]);

    $response = $this->getJson('/api/v1/employees/' . $this->employee1->id . '/violations');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(3);

    foreach ($response->json('data') as $violation) {
        expect($violation['employee_id'])->toBe($this->employee1->id);
    }
});

test('can acknowledge violation', function () {
    $violation = AttendanceViolation::factory()->create([
        'employee_id' => $this->employee1->id,
        'status' => 'pending',
    ]);

    $response = $this->postJson('/api/v1/violations/' . $violation->id . '/acknowledge', [
        'notes' => 'Acknowledged by manager',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Violation acknowledged successfully.',
        ]);

    $violation->refresh();
    expect($violation->status)->toBe('acknowledged')
        ->and($violation->notes)->toBe('Acknowledged by manager');
});

test('cannot acknowledge non-pending violation', function () {
    $violation = AttendanceViolation::factory()->acknowledged()->create([
        'employee_id' => $this->employee1->id,
    ]);

    $response = $this->postJson('/api/v1/violations/' . $violation->id . '/acknowledge', [
        'notes' => 'Trying to re-acknowledge',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Only pending violations can be acknowledged.',
        ]);
});

test('can dispute violation', function () {
    $violation = AttendanceViolation::factory()->create([
        'employee_id' => $this->employee1->id,
        'status' => 'pending',
    ]);

    $response = $this->postJson('/api/v1/violations/' . $violation->id . '/dispute', [
        'notes' => 'I was stuck in traffic due to emergency',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Violation disputed successfully.',
        ]);

    $violation->refresh();
    expect($violation->status)->toBe('disputed')
        ->and($violation->notes)->toBe('I was stuck in traffic due to emergency');
});

test('cannot dispute violation without notes', function () {
    $violation = AttendanceViolation::factory()->create([
        'employee_id' => $this->employee1->id,
        'status' => 'pending',
    ]);

    $response = $this->postJson('/api/v1/violations/' . $violation->id . '/dispute', [
        'notes' => '',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['notes']);
});

test('cannot dispute resolved violation', function () {
    $violation = AttendanceViolation::factory()->create([
        'employee_id' => $this->employee1->id,
        'status' => 'resolved',
    ]);

    $response = $this->postJson('/api/v1/violations/' . $violation->id . '/dispute', [
        'notes' => 'Trying to dispute resolved violation',
    ]);

    $response->assertStatus(422)
        ->assertJson([
            'message' => 'Resolved violations cannot be disputed.',
        ]);
});

test('pagination works correctly', function () {
    AttendanceViolation::factory()->count(25)->create([
        'employee_id' => $this->employee1->id,
    ]);

    $response = $this->getJson('/api/v1/violations?per_page=10');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(10)
        ->and($response->json('meta.total'))->toBe(25)
        ->and($response->json('meta.per_page'))->toBe(10);
});
