<?php

namespace Database\Factories\Domain\Attendance\Models;

use App\Domain\Attendance\Models\AttendanceCorrection;
use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Attendance\Models\AttendanceCorrection>
 */
class AttendanceCorrectionFactory extends Factory
{
    protected $model = AttendanceCorrection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['missing_checkout', 'wrong_time', 'duplicate_record', 'missing_record', 'other']);

        return [
            'employee_id' => Employee::factory(),
            'attendance_record_id' => $type !== 'missing_record' ? AttendanceRecord::factory() : null,
            'type' => $type,
            'status' => 'pending',
            'original_data' => $this->getOriginalData($type),
            'proposed_data' => $this->getProposedData($type),
            'reason' => $this->faker->sentence(),
            'supporting_document_path' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_notes' => null,
            'applied_at' => null,
        ];
    }

    /**
     * State: Approved correction
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'reviewed_by' => User::factory(),
            'reviewed_at' => now()->subHours(1),
            'review_notes' => $this->faker->sentence(),
        ]);
    }

    /**
     * State: Rejected correction
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'reviewed_by' => User::factory(),
            'reviewed_at' => now()->subHours(1),
            'review_notes' => $this->faker->sentence(),
        ]);
    }

    /**
     * State: Applied correction
     */
    public function applied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'applied',
            'reviewed_by' => User::factory(),
            'reviewed_at' => now()->subHours(2),
            'review_notes' => $this->faker->sentence(),
            'applied_at' => now()->subHours(1),
        ]);
    }

    /**
     * State: Missing checkout correction
     */
    public function missingCheckout(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'missing_checkout',
            'attendance_record_id' => AttendanceRecord::factory(),
            'original_data' => [
                'check_in_time' => '09:00:00',
                'check_out_time' => null,
            ],
            'proposed_data' => [
                'check_out_time' => '17:00:00',
            ],
        ]);
    }

    /**
     * State: Wrong time correction
     */
    public function wrongTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'wrong_time',
            'attendance_record_id' => AttendanceRecord::factory(),
            'original_data' => [
                'check_in_time' => '09:30:00',
            ],
            'proposed_data' => [
                'check_in_time' => '09:00:00',
            ],
        ]);
    }

    /**
     * Get original data based on correction type
     */
    protected function getOriginalData(string $type): ?array
    {
        return match ($type) {
            'missing_checkout' => [
                'check_in_time' => '09:00:00',
                'check_out_time' => null,
            ],
            'wrong_time' => [
                'check_in_time' => '09:30:00',
            ],
            'duplicate_record' => [
                'check_in_time' => '09:00:00',
                'check_out_time' => '17:00:00',
            ],
            'missing_record' => null,
            'other' => [
                'description' => $this->faker->sentence(),
            ],
        };
    }

    /**
     * Get proposed data based on correction type
     */
    protected function getProposedData(string $type): array
    {
        return match ($type) {
            'missing_checkout' => [
                'check_out_time' => '17:00:00',
            ],
            'wrong_time' => [
                'check_in_time' => '09:00:00',
            ],
            'duplicate_record' => [
                'action' => 'delete',
            ],
            'missing_record' => [
                'date' => now()->toDateString(),
                'check_in_time' => '09:00:00',
                'check_out_time' => '17:00:00',
            ],
            'other' => [
                'description' => $this->faker->sentence(),
            ],
        };
    }
}
