<?php

use App\Domain\Attendance\Events\CorrectionApproved;
use App\Domain\Attendance\Events\CorrectionRejected;
use App\Domain\Attendance\Events\CorrectionRequested;
use App\Domain\Attendance\Models\AttendanceCorrection;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Event::fake();
});

test('employee can create correction request', function () {
    $employee = Employee::factory()->create();
    $record = AttendanceRecord::factory()->create(['employee_id' => $employee->id]);

    $response = $this->actingAs(User::factory()->create())
        ->postJson('/api/v1/corrections', [
            'employee_id' => $employee->id,
            'attendance_record_id' => $record->id,
            'type' => 'wrong_time',
            'proposed_data' => [
                'check_in_time' => '09:00:00',
            ],
            'reason' => 'The system recorded wrong time due to device malfunction',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'id',
            'employee_id',
            'type',
            'status',
            'reason',
        ]);

    expect(AttendanceCorrection::count())->toBe(1);

    Event::assertDispatched(CorrectionRequested::class);
});

test('employee can upload supporting document with correction request', function () {
    $employee = Employee::factory()->create();
    $file = UploadedFile::fake()->create('evidence.pdf', 100);

    $response = $this->actingAs(User::factory()->create())
        ->postJson('/api/v1/corrections', [
            'employee_id' => $employee->id,
            'type' => 'missing_record',
            'proposed_data' => [
                'check_in_time' => '09:00:00',
                'check_out_time' => '17:00:00',
            ],
            'reason' => 'Device was offline, attaching manager approval email',
            'supporting_document' => $file,
        ]);

    $response->assertStatus(201);

    $correction = AttendanceCorrection::first();
    expect($correction->supporting_document_path)->not->toBeNull();
    Storage::disk('local')->assertExists($correction->supporting_document_path);
});

test('employee can update pending correction request', function () {
    $correction = AttendanceCorrection::factory()->create(['status' => 'pending']);

    $response = $this->actingAs(User::factory()->create())
        ->putJson("/api/v1/corrections/{$correction->id}", [
            'reason' => 'Updated reason with more details about the incident',
        ]);

    $response->assertStatus(200);

    $correction->refresh();
    expect($correction->reason)->toBe('Updated reason with more details about the incident');
});

test('employee cannot update approved correction request', function () {
    $correction = AttendanceCorrection::factory()->approved()->create();

    $response = $this->actingAs(User::factory()->create())
        ->putJson("/api/v1/corrections/{$correction->id}", [
            'reason' => 'Trying to update approved correction',
        ]);

    $response->assertStatus(403);
});

test('employee can cancel pending correction request', function () {
    $correction = AttendanceCorrection::factory()->create([
        'status' => 'pending',
        'supporting_document_path' => 'corrections/documents/test.pdf',
    ]);

    Storage::disk('local')->put($correction->supporting_document_path, 'fake content');

    $response = $this->actingAs(User::factory()->create())
        ->deleteJson("/api/v1/corrections/{$correction->id}");

    $response->assertStatus(200);

    expect(AttendanceCorrection::find($correction->id))->toBeNull();
    Storage::disk('local')->assertMissing($correction->supporting_document_path);
});

test('employee cannot cancel approved correction request', function () {
    $correction = AttendanceCorrection::factory()->approved()->create();

    $response = $this->actingAs(User::factory()->create())
        ->deleteJson("/api/v1/corrections/{$correction->id}");

    $response->assertStatus(422);
    expect(AttendanceCorrection::find($correction->id))->not->toBeNull();
});

test('manager can list pending corrections for their team', function () {
    $manager = User::factory()->create();
    $employee = Employee::factory()->create(['manager_id' => $manager->id]);

    AttendanceCorrection::factory()->count(3)->create([
        'employee_id' => $employee->id,
        'status' => 'pending',
    ]);

    AttendanceCorrection::factory()->create([
        'employee_id' => $employee->id,
        'status' => 'approved',
    ]);

    $response = $this->actingAs($manager)
        ->getJson('/api/v1/manager/corrections?status=pending');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

test('manager can approve correction request', function () {
    $manager = User::factory()->create();
    $employee = Employee::factory()->create(['manager_id' => $manager->id]);
    $correction = AttendanceCorrection::factory()->create([
        'employee_id' => $employee->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($manager)
        ->postJson("/api/v1/manager/corrections/{$correction->id}/approve", [
            'notes' => 'Approved after verifying with security logs',
        ]);

    $response->assertStatus(200);

    $correction->refresh();
    expect($correction->status)->toBe('applied')
        ->and($correction->reviewed_by)->toBe($manager->id)
        ->and($correction->review_notes)->toBe('Approved after verifying with security logs');

    Event::assertDispatched(CorrectionApproved::class);
});

test('manager can reject correction request with reason', function () {
    $manager = User::factory()->create();
    $employee = Employee::factory()->create(['manager_id' => $manager->id]);
    $correction = AttendanceCorrection::factory()->create([
        'employee_id' => $employee->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($manager)
        ->postJson("/api/v1/manager/corrections/{$correction->id}/reject", [
            'notes' => 'Cannot verify your claim with available records',
        ]);

    $response->assertStatus(200);

    $correction->refresh();
    expect($correction->status)->toBe('rejected')
        ->and($correction->reviewed_by)->toBe($manager->id)
        ->and($correction->review_notes)->toBe('Cannot verify your claim with available records');

    Event::assertDispatched(CorrectionRejected::class);
});

test('rejection requires notes', function () {
    $manager = User::factory()->create();
    $employee = Employee::factory()->create(['manager_id' => $manager->id]);
    $correction = AttendanceCorrection::factory()->create([
        'employee_id' => $employee->id,
        'status' => 'pending',
    ]);

    $response = $this->actingAs($manager)
        ->postJson("/api/v1/manager/corrections/{$correction->id}/reject");

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['notes']);
});

test('manager cannot approve already reviewed correction', function () {
    $manager = User::factory()->create();
    $employee = Employee::factory()->create(['manager_id' => $manager->id]);
    $correction = AttendanceCorrection::factory()->approved()->create([
        'employee_id' => $employee->id,
    ]);

    $response = $this->actingAs($manager)
        ->postJson("/api/v1/manager/corrections/{$correction->id}/approve");

    $response->assertStatus(403);
});

test('employee can filter corrections by status', function () {
    $employee = Employee::factory()->create();

    AttendanceCorrection::factory()->count(2)->create([
        'employee_id' => $employee->id,
        'status' => 'pending',
    ]);

    AttendanceCorrection::factory()->count(3)->create([
        'employee_id' => $employee->id,
        'status' => 'approved',
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->getJson("/api/v1/corrections?employee_id={$employee->id}&status=approved");

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

test('employee can filter corrections by type', function () {
    $employee = Employee::factory()->create();

    AttendanceCorrection::factory()->count(2)->create([
        'employee_id' => $employee->id,
        'type' => 'missing_checkout',
    ]);

    AttendanceCorrection::factory()->count(3)->create([
        'employee_id' => $employee->id,
        'type' => 'wrong_time',
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->getJson("/api/v1/corrections?employee_id={$employee->id}&type=wrong_time");

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

test('employee can download supporting document', function () {
    $correction = AttendanceCorrection::factory()->create([
        'supporting_document_path' => 'corrections/documents/test.pdf',
    ]);

    Storage::disk('local')->put($correction->supporting_document_path, 'PDF content here');

    $response = $this->actingAs(User::factory()->create())
        ->getJson("/api/v1/corrections/{$correction->id}/document");

    $response->assertStatus(200)
        ->assertDownload();
});

test('downloading non-existent document returns 404', function () {
    $correction = AttendanceCorrection::factory()->create([
        'supporting_document_path' => null,
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->getJson("/api/v1/corrections/{$correction->id}/document");

    $response->assertStatus(404);
});
