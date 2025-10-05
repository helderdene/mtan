<?php

namespace Database\Factories;

use App\Models\Tenant\AttendanceRecord;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant\AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $directions = ['check-in', 'check-out', 'break-start', 'break-end'];
        $direction = fake()->randomElement($directions);

        return [
            'employee_id' => Employee::factory(),
            'device_id' => Device::factory(),
            'recorded_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'direction' => $direction,
            'recognition_score' => fake()->randomFloat(4, 0.8, 1.0),
            'confidence_score' => fake()->numberBetween(50, 100),
            'detection_reason' => $this->generateDetectionReason($direction),
            'record_id' => 'REC' . fake()->unique()->numberBetween(100000, 999999),
            'person_name' => fake()->name(),
            'device_name' => 'Device ' . fake()->numberBetween(1, 10),
            'verify_status' => fake()->randomElement(['1', '0']),
            'temperature' => fake()->randomFloat(1, 36.0, 37.5),
            'mask_status' => fake()->boolean(80),
            'photo_path' => null,
        ];
    }

    /**
     * Generate realistic detection reason based on direction
     */
    protected function generateDetectionReason(string $direction): string
    {
        $reasons = [
            'check-in' => [
                'High confidence: Near shift start, last action was check-out',
                'Medium confidence: Morning time (before 12 PM), no recent records',
                'High confidence: First record of the day',
            ],
            'check-out' => [
                'High confidence: Near shift end, last action was check-in',
                'Medium confidence: Evening time (after 5 PM), worked > 4 hours',
                'High confidence: Last action was break-end, shift ending',
            ],
            'break-start' => [
                'Medium confidence: Mid-shift timing, last action was check-in',
                'High confidence: Near typical break time, worked > 2 hours',
            ],
            'break-end' => [
                'High confidence: Last action was break-start, break duration normal',
                'Medium confidence: Break duration < 1 hour, resuming work',
            ],
        ];

        return fake()->randomElement($reasons[$direction]);
    }

    /**
     * State for check-in records
     */
    public function checkIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'check-in',
            'detection_reason' => $this->generateDetectionReason('check-in'),
        ]);
    }

    /**
     * State for check-out records
     */
    public function checkOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'check-out',
            'detection_reason' => $this->generateDetectionReason('check-out'),
        ]);
    }

    /**
     * State for break-start records
     */
    public function breakStart(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'break-start',
            'detection_reason' => $this->generateDetectionReason('break-start'),
        ]);
    }

    /**
     * State for break-end records
     */
    public function breakEnd(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'break-end',
            'detection_reason' => $this->generateDetectionReason('break-end'),
        ]);
    }

    /**
     * State for high confidence detection
     */
    public function highConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence_score' => fake()->numberBetween(80, 100),
        ]);
    }

    /**
     * State for low confidence detection
     */
    public function lowConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence_score' => fake()->numberBetween(0, 49),
        ]);
    }
}
