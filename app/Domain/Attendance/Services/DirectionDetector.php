<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\DTOs\DirectionResult;
use App\Domain\Shift\Models\Shift;
use App\Domain\Shift\Models\Employee;
use App\Domain\Shift\Services\FlexibleShiftValidator;
use App\Domain\Shift\Services\OverrideService;
use App\Models\Tenant\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Service for detecting attendance direction (check-in, check-out, break-start, break-end)
 * using multi-factor weighted scoring algorithm.
 *
 * Algorithm Overview:
 * ------------------
 * The detector uses 5 weighted factors to determine the most likely direction:
 *
 * 1. Last Record Analysis (30%): Logical transitions based on previous direction
 *    - After check-in → favor check-out or break-start
 *    - After check-out → favor check-in
 *    - After break-start → favor break-end
 *    - After break-end → favor check-out
 *
 * 2. Shift Timing Proximity (35%): Time-based scoring relative to shift schedule
 *    - Near shift start (±30 min) → favor check-in
 *    - Near shift end (±30 min) → favor check-out
 *    - Near break start (±15 min) → favor break-start
 *    - Near break end (±15 min) → favor break-end
 *
 * 3. Work Duration (15%): Realistic work/break duration validation
 *    - < 30 min since check-in → penalize check-out
 *    - ≥ 4 hours since check-in → favor check-out
 *    - 1-120 min since break-start → favor break-end
 *
 * 4. Historical Pattern Analysis (20%): Employee's typical check-in/out times from last 30 days
 *    - Within 1σ (68% of data) → 20 points
 *    - Within 2σ (95% of data) → 15 points
 *    - Within 3σ (99.7% of data) → 10 points
 *    - Outside 3σ → 5 points
 *    - Unreliable pattern (< 7 records) → 10 points (neutral)
 *
 * The algorithm multiplies each factor's score (0-100) by its weight, sums them
 * for each direction, and selects the direction with the highest aggregate score.
 * Confidence is the final score (0-100) indicating detection certainty.
 *
 * Overnight Shift Support:
 * -----------------------
 * The detector handles shifts crossing midnight by comparing timestamp dates:
 * - If timestamp time < 12 and shift end < 12: shift started yesterday
 * - Otherwise: shift ends tomorrow (standard overnight shift)
 *
 * Edge Cases Handled:
 * ------------------
 * - No shift assigned: Uses fallback logic only
 * - No previous records: Strongly favors check-in (first record of day)
 * - Multiple same-direction records: Suggests opposite direction
 * - Events far from shift: Lower confidence but still returns best guess
 */
class DirectionDetector
{
    /**
     * Scoring weights for each factor (must total 100)
     *
     * @var array{last_record: int, shift_timing: int, work_duration: int, pattern: int}
     */
    protected array $weights;

    /**
     * Pattern analyzer service
     *
     * @var PatternAnalyzer|null
     */
    protected ?PatternAnalyzer $patternAnalyzer;

    /**
     * Override service for handling shift overrides
     *
     * @var OverrideService
     */
    protected OverrideService $overrideService;

    /**
     * Flexible shift validator service
     *
     * @var FlexibleShiftValidator
     */
    protected FlexibleShiftValidator $flexibleShiftValidator;

    /**
     * Cache for last attendance records (prevents duplicate queries in same request)
     *
     * @var array
     */
    protected array $lastRecordCache = [];

    /**
     * Create a new DirectionDetector instance
     *
     * @param PatternAnalyzer|null $patternAnalyzer Pattern analyzer service (optional, will be auto-injected)
     * @param OverrideService|null $overrideService Override service (optional, will be auto-injected)
     * @param FlexibleShiftValidator|null $flexibleShiftValidator Flexible shift validator (optional, will be auto-injected)
     * @param array $weights Custom scoring weights (optional). Defaults:
     *                       - last_record: 30%
     *                       - shift_timing: 35%
     *                       - work_duration: 15%
     *                       - pattern: 20%
     */
    public function __construct(
        ?PatternAnalyzer $patternAnalyzer = null,
        ?OverrideService $overrideService = null,
        ?FlexibleShiftValidator $flexibleShiftValidator = null,
        array $weights = []
    ) {
        $this->patternAnalyzer = $patternAnalyzer ?? new PatternAnalyzer();
        $this->overrideService = $overrideService ?? new OverrideService();
        $this->flexibleShiftValidator = $flexibleShiftValidator ?? new FlexibleShiftValidator();

        $this->weights = array_merge([
            'last_record' => 30,    // Logical flow: what should come after last action
            'shift_timing' => 35,   // Proximity to shift start/end/break times
            'work_duration' => 15,  // Realistic work/break duration constraints
            'pattern' => 20,        // Historical pattern analysis (30-day avg)
        ], $weights);
    }

    /**
     * Detect the most likely direction for an attendance event
     *
     * @param Employee $employee The employee
     * @param Carbon $timestamp The event timestamp
     * @param Shift|null $shift The employee's shift (optional)
     * @return DirectionResult
     */
    public function detect(Employee $employee, Carbon $timestamp, ?Shift $shift): DirectionResult
    {
        // Check for shift overrides before proceeding
        $override = null;
        $effectiveShift = null;

        if ($shift) {
            $override = $this->overrideService->getActiveOverride(
                $timestamp->copy()->startOfDay(),
                $shift,
                $employee
            );

            // If holiday or off-day, employee should not be working
            // Log warning and process with low confidence
            if ($override && in_array($override->type, ['holiday', 'off-day'])) {
                Log::warning("Attendance event received on {$override->type}", [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'date' => $timestamp->toDateString(),
                    'override_type' => $override->type,
                    'override_reason' => $override->reason,
                ]);
            }

            // Get effective shift times (accounting for half-day/custom-shift)
            $effectiveShift = $this->overrideService->getEffectiveShiftTimes(
                $timestamp->copy()->startOfDay(),
                $shift,
                $employee
            );

            // If no work required (holiday/off-day), use original shift for scoring
            // but with reduced confidence
            if ($effectiveShift === null) {
                $effectiveShift = null;
            }
        }

        // Get the last attendance record for context
        $lastRecord = $this->getLastAttendanceRecord($employee, $timestamp);

        // Initialize scores for all possible directions
        $scores = [
            'check-in' => 0,
            'check-out' => 0,
            'break-start' => 0,
            'break-end' => 0,
        ];

        // Calculate scores for each factor (use effective shift for timing if available)
        $lastRecordScores = $this->calculateLastRecordScore($lastRecord);
        $shiftTimingScores = $this->calculateShiftTimingScore($timestamp, $shift, $lastRecord, $effectiveShift);
        $workDurationScores = $this->calculateWorkDurationScore($lastRecord, $timestamp);
        $patternScores = $this->calculatePatternScore($employee, $timestamp);

        // Apply weights and aggregate scores
        foreach ($scores as $direction => &$score) {
            $score += ($lastRecordScores[$direction] ?? 0) * ($this->weights['last_record'] / 100);
            $score += ($shiftTimingScores[$direction] ?? 0) * ($this->weights['shift_timing'] / 100);
            $score += ($workDurationScores[$direction] ?? 0) * ($this->weights['work_duration'] / 100);
            $score += ($patternScores[$direction] ?? 0) * ($this->weights['pattern'] / 100);
        }

        // Select the direction with the highest score
        arsort($scores);
        $bestDirection = array_key_first($scores);
        $bestScore = $scores[$bestDirection];

        // Calculate confidence (0-100)
        $confidence = (int) round($bestScore);

        // Reduce confidence if on holiday/off-day
        if ($override && in_array($override->type, ['holiday', 'off-day'])) {
            $confidence = min(50, $confidence); // Cap at 50% on holidays/off-days
        }

        // Build human-readable reason
        $reason = $this->buildDetectionReason($bestDirection, $lastRecord, $shift, $timestamp, $confidence);

        // Prepare detailed score breakdown
        $scoreBreakdown = [
            'last_record' => $lastRecordScores[$bestDirection] ?? 0,
            'shift_timing' => $shiftTimingScores[$bestDirection] ?? 0,
            'work_duration' => $workDurationScores[$bestDirection] ?? 0,
            'pattern' => $patternScores[$bestDirection] ?? 0,
            'total' => $bestScore,
            'all_directions' => $scores,
            'override_applied' => $override !== null,
            'override_type' => $override?->type,
        ];

        return new DirectionResult(
            direction: $bestDirection,
            confidence: $confidence,
            scores: $scoreBreakdown,
            reason: $reason
        );
    }

    /**
     * Get the most recent attendance record for an employee before the given timestamp
     *
     * Uses in-memory caching to prevent duplicate queries within the same request.
     *
     * @param Employee $employee
     * @param Carbon $timestamp
     * @return AttendanceRecord|null
     */
    protected function getLastAttendanceRecord(Employee $employee, Carbon $timestamp): ?AttendanceRecord
    {
        $cacheKey = "employee_{$employee->id}_before_" . $timestamp->timestamp;

        // Check in-memory cache first
        if (array_key_exists($cacheKey, $this->lastRecordCache)) {
            return $this->lastRecordCache[$cacheKey];
        }

        // Query database with optimized index usage
        $record = AttendanceRecord::where('employee_id', $employee->id)
            ->where('recorded_at', '<', $timestamp)
            ->orderBy('recorded_at', 'desc')
            ->first();

        // Cache the result (including null) for this request
        $this->lastRecordCache[$cacheKey] = $record;

        return $record;
    }

    /**
     * Calculate scores based on last attendance record
     *
     * @param AttendanceRecord|null $lastRecord
     * @return array
     */
    protected function calculateLastRecordScore(?AttendanceRecord $lastRecord): array
    {
        if (!$lastRecord) {
            // First record of the day - strongly favor check-in
            return [
                'check-in' => 100,
                'check-out' => 0,
                'break-start' => 0,
                'break-end' => 0,
            ];
        }

        // Score based on logical transitions
        return match ($lastRecord->direction) {
            'check-in' => [
                'check-in' => 0,
                'check-out' => 100,
                'break-start' => 100,
                'break-end' => 0,
            ],
            'check-out' => [
                'check-in' => 100,
                'check-out' => 0,
                'break-start' => 0,
                'break-end' => 0,
            ],
            'break-start' => [
                'check-in' => 0,
                'check-out' => 0,
                'break-start' => 0,
                'break-end' => 100,
            ],
            'break-end' => [
                'check-in' => 0,
                'check-out' => 100,
                'break-start' => 0,
                'break-end' => 0,
            ],
            default => [
                'check-in' => 50,
                'check-out' => 50,
                'break-start' => 50,
                'break-end' => 50,
            ],
        };
    }

    /**
     * Calculate scores based on shift timing proximity
     *
     * @param Carbon $timestamp
     * @param Shift|null $shift
     * @param AttendanceRecord|null $lastRecord
     * @param \App\Domain\Shift\DTOs\EffectiveShift|null $effectiveShift
     * @return array
     */
    protected function calculateShiftTimingScore(Carbon $timestamp, ?Shift $shift, ?AttendanceRecord $lastRecord, $effectiveShift = null): array
    {
        if (!$shift) {
            // No shift - return neutral scores
            return [
                'check-in' => 50,
                'check-out' => 50,
                'break-start' => 50,
                'break-end' => 50,
            ];
        }

        $scores = [
            'check-in' => 0,
            'check-out' => 0,
            'break-start' => 0,
            'break-end' => 0,
        ];

        // Handle flexible shifts
        if ($shift->isFlexible()) {
            // For flexible shifts, check-in can be anywhere within the flexible window
            $earliestCheckIn = $this->flexibleShiftValidator->getEarliestCheckInTime($shift, $timestamp);
            $latestCheckIn = $this->flexibleShiftValidator->getLatestCheckInTime($shift, $timestamp);

            if ($earliestCheckIn && $latestCheckIn) {
                // Score check-in highly if within flexible window
                if ($timestamp->between($earliestCheckIn, $latestCheckIn)) {
                    $scores['check-in'] = 100;
                } else {
                    // Penalize if outside window
                    $scores['check-in'] = 20;
                }

                // Calculate expected end time based on flexible shift duration
                $expectedEndTime = $this->flexibleShiftValidator->calculateExpectedEndTime($shift, $timestamp);
                $minutesFromExpectedEnd = abs($timestamp->diffInMinutes($expectedEndTime));

                if ($minutesFromExpectedEnd <= 30) {
                    $scores['check-out'] = 100 - ($minutesFromExpectedEnd * 2);
                }
            }

            // Break scoring remains the same for flexible shifts
            if ($shift->break_start && $shift->break_end) {
                $this->scoreBreakTimes($timestamp, $shift, $scores);
            }

            return $scores;
        }

        // Use effective shift times if override is present, otherwise use regular shift
        if ($effectiveShift) {
            $shiftStart = $effectiveShift->startTime;
            $shiftEnd = $effectiveShift->endTime;
        } else {
            // Parse shift times using timestamp's date as base
            $shiftStart = Carbon::parse($timestamp->format('Y-m-d') . ' ' . $shift->start_time);
            $shiftEnd = Carbon::parse($timestamp->format('Y-m-d') . ' ' . $shift->end_time);

            // Handle overnight shifts
            $isOvernightShift = Carbon::parse($shift->end_time)->lessThan(Carbon::parse($shift->start_time));
            if ($isOvernightShift) {
                // If timestamp time is closer to end_time than start_time, it's the next day portion
                $timestampTime = Carbon::parse($timestamp->format('H:i:s'));
                $shiftStartTime = Carbon::parse($shift->start_time);
                $shiftEndTime = Carbon::parse($shift->end_time);

                // If current time is before noon and shift end is before noon, we're in the next-day portion
                if ($timestampTime->hour < 12 && $shiftEndTime->hour < 12) {
                    // Shift start was yesterday
                    $shiftStart->subDay();
                } else {
                    // Normal case: shift end is tomorrow
                    $shiftEnd->addDay();
                }
            }
        }

        // Check-in scoring (within 30 min of shift start)
        $minutesFromStart = abs($timestamp->diffInMinutes($shiftStart));
        if ($minutesFromStart <= 30) {
            $scores['check-in'] = 100 - ($minutesFromStart * 2); // Decrease by 2 points per minute
        }

        // Check-out scoring (within 30 min of shift end)
        $minutesFromEnd = abs($timestamp->diffInMinutes($shiftEnd));
        if ($minutesFromEnd <= 30) {
            $scores['check-out'] = 100 - ($minutesFromEnd * 2);
        }

        // Break scoring (if shift has break times)
        if ($shift->break_start && $shift->break_end) {
            $this->scoreBreakTimes($timestamp, $shift, $scores);
        }

        return $scores;
    }

    /**
     * Score break times for both flexible and fixed shifts
     *
     * @param Carbon $timestamp
     * @param Shift $shift
     * @param array &$scores
     * @return void
     */
    protected function scoreBreakTimes(Carbon $timestamp, Shift $shift, array &$scores): void
    {
        $breakStart = Carbon::parse($timestamp->format('Y-m-d') . ' ' . $shift->break_start);
        $breakEnd = Carbon::parse($timestamp->format('Y-m-d') . ' ' . $shift->break_end);

        $minutesFromBreakStart = abs($timestamp->diffInMinutes($breakStart));
        if ($minutesFromBreakStart <= 15) {
            $scores['break-start'] = 100 - ($minutesFromBreakStart * 4);
        }

        $minutesFromBreakEnd = abs($timestamp->diffInMinutes($breakEnd));
        if ($minutesFromBreakEnd <= 15) {
            $scores['break-end'] = 100 - ($minutesFromBreakEnd * 4);
        }
    }

    /**
     * Calculate scores based on work duration
     *
     * @param AttendanceRecord|null $lastRecord
     * @param Carbon $timestamp
     * @return array
     */
    protected function calculateWorkDurationScore(?AttendanceRecord $lastRecord, Carbon $timestamp): array
    {
        $scores = [
            'check-in' => 50,
            'check-out' => 50,
            'break-start' => 50,
            'break-end' => 50,
        ];

        if (!$lastRecord) {
            return $scores;
        }

        $minutesSinceLastRecord = $lastRecord->recorded_at->diffInMinutes($timestamp);

        // If last action was check-in, evaluate check-out based on work duration
        if ($lastRecord->direction === 'check-in') {
            if ($minutesSinceLastRecord < 30) {
                // Too early for check-out
                $scores['check-out'] = 0;
            } elseif ($minutesSinceLastRecord >= 240) {
                // 4+ hours worked - good for check-out
                $scores['check-out'] = 100;
            } else {
                // Between 30 min and 4 hours - scale score
                $scores['check-out'] = (int) (($minutesSinceLastRecord / 240) * 100);
            }
        }

        // If last action was break-start, evaluate break-end based on break duration
        if ($lastRecord->direction === 'break-start') {
            if ($minutesSinceLastRecord < 1) {
                // Too early for break-end
                $scores['break-end'] = 0;
            } elseif ($minutesSinceLastRecord <= 120) {
                // Normal break duration (1 min to 2 hours)
                $scores['break-end'] = 100;
            } else {
                // Extended break - reduce score
                $scores['break-end'] = 30;
            }
        }

        return $scores;
    }

    /**
     * Calculate scores based on historical pattern analysis
     *
     * Uses PatternAnalyzer to score each direction based on employee's
     * typical check-in/check-out times over the last 30 days.
     *
     * @param Employee $employee
     * @param Carbon $timestamp
     * @return array
     */
    protected function calculatePatternScore(Employee $employee, Carbon $timestamp): array
    {
        $scores = [
            'check-in' => 0,
            'check-out' => 0,
            'break-start' => 0,
            'break-end' => 0,
        ];

        // Score check-in direction based on pattern
        $checkInScore = $this->patternAnalyzer->scorePattern($employee, $timestamp, 'check-in');
        $scores['check-in'] = $checkInScore * 5; // Convert 0-20 to 0-100 scale

        // Score check-out direction based on pattern
        $checkOutScore = $this->patternAnalyzer->scorePattern($employee, $timestamp, 'check-out');
        $scores['check-out'] = $checkOutScore * 5; // Convert 0-20 to 0-100 scale

        // Breaks don't have historical patterns, use neutral score
        $scores['break-start'] = 50;
        $scores['break-end'] = 50;

        return $scores;
    }

    /**
     * Build a human-readable explanation for the detection
     *
     * @param string $direction
     * @param AttendanceRecord|null $lastRecord
     * @param Shift|null $shift
     * @param Carbon $timestamp
     * @param int $confidence
     * @return string
     */
    protected function buildDetectionReason(
        string $direction,
        ?AttendanceRecord $lastRecord,
        ?Shift $shift,
        Carbon $timestamp,
        int $confidence
    ): string {
        $level = $confidence >= 80 ? 'High' : ($confidence >= 50 ? 'Medium' : 'Low');
        $parts = ["{$level} confidence:"];

        // Add shift timing context
        if ($shift) {
            $timeOfDay = $timestamp->format('H:i');
            $parts[] = "time {$timeOfDay}";

            if ($direction === 'check-in' && abs(Carbon::parse($timeOfDay)->diffInMinutes(Carbon::parse($shift->start_time))) <= 30) {
                $parts[] = "near shift start ({$shift->start_time})";
            } elseif ($direction === 'check-out' && abs(Carbon::parse($timeOfDay)->diffInMinutes(Carbon::parse($shift->end_time))) <= 30) {
                $parts[] = "near shift end ({$shift->end_time})";
            }
        } else {
            $parts[] = $timestamp->format('g:i A');
        }

        // Add last record context
        if ($lastRecord) {
            $parts[] = "last action was {$lastRecord->direction}";
        } else {
            $parts[] = "first record of day";
        }

        return implode(', ', $parts);
    }
}
