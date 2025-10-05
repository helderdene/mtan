<?php

namespace App\Domain\Shift\DTOs;

use App\Domain\Shift\Models\Shift;
use App\Domain\Shift\Models\ShiftOverride;
use Carbon\Carbon;

/**
 * Represents the effective shift times for a given date,
 * accounting for any shift overrides (half-day, custom-shift, etc.)
 */
class EffectiveShift
{
    public function __construct(
        public readonly Carbon $startTime,
        public readonly Carbon $endTime,
        public readonly bool $isModified,
        public readonly ?ShiftOverride $override,
        public readonly ?Shift $originalShift,
    ) {
    }

    /**
     * Create an EffectiveShift from a regular shift without modifications.
     */
    public static function fromShift(Shift $shift, Carbon $date): self
    {
        $startTime = Carbon::parse($date->format('Y-m-d').' '.$shift->start_time);
        $endTime = Carbon::parse($date->format('Y-m-d').' '.$shift->end_time);

        // Handle overnight shifts
        if ($shift->is_overnight && $endTime->lt($startTime)) {
            $endTime->addDay();
        }

        return new self(
            startTime: $startTime,
            endTime: $endTime,
            isModified: false,
            override: null,
            originalShift: $shift,
        );
    }

    /**
     * Create an EffectiveShift from a shift override.
     */
    public static function fromOverride(ShiftOverride $override, Shift $shift, Carbon $date): self
    {
        $startTime = Carbon::parse($date->format('Y-m-d').' '.$override->custom_start_time);
        $endTime = Carbon::parse($date->format('Y-m-d').' '.$override->custom_end_time);

        // Handle overnight custom shifts
        if ($endTime->lt($startTime)) {
            $endTime->addDay();
        }

        return new self(
            startTime: $startTime,
            endTime: $endTime,
            isModified: true,
            override: $override,
            originalShift: $shift,
        );
    }

    /**
     * Get the total shift duration in minutes.
     */
    public function getDurationMinutes(): int
    {
        return $this->startTime->diffInMinutes($this->endTime);
    }

    /**
     * Check if the given timestamp is within the shift times.
     */
    public function isWithinShift(Carbon $timestamp): bool
    {
        return $timestamp->between($this->startTime, $this->endTime);
    }

    /**
     * Get minutes from shift start.
     */
    public function getMinutesFromStart(Carbon $timestamp): int
    {
        return $this->startTime->diffInMinutes($timestamp, false);
    }

    /**
     * Get minutes to shift end.
     */
    public function getMinutesToEnd(Carbon $timestamp): int
    {
        return $timestamp->diffInMinutes($this->endTime, false);
    }

    /**
     * Check if timestamp is near shift start (within threshold).
     */
    public function isNearStart(Carbon $timestamp, int $thresholdMinutes = 30): bool
    {
        $minutesFromStart = abs($this->getMinutesFromStart($timestamp));

        return $minutesFromStart <= $thresholdMinutes;
    }

    /**
     * Check if timestamp is near shift end (within threshold).
     */
    public function isNearEnd(Carbon $timestamp, int $thresholdMinutes = 30): bool
    {
        $minutesToEnd = abs($this->getMinutesToEnd($timestamp));

        return $minutesToEnd <= $thresholdMinutes;
    }

    /**
     * Convert to array representation.
     */
    public function toArray(): array
    {
        return [
            'start_time' => $this->startTime->format('Y-m-d H:i:s'),
            'end_time' => $this->endTime->format('Y-m-d H:i:s'),
            'is_modified' => $this->isModified,
            'override_type' => $this->override?->type,
            'override_reason' => $this->override?->reason,
            'original_shift_id' => $this->originalShift?->id,
            'duration_minutes' => $this->getDurationMinutes(),
        ];
    }
}
