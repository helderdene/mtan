<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BreakDurationValid implements ValidationRule
{
    protected string|null $breakStart;
    protected int $minDuration = 1; // minutes
    protected int $maxDuration = 120; // 2 hours

    public function __construct(?string $breakStart)
    {
        $this->breakStart = $breakStart;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value || ! $this->breakStart) {
            return; // Skip if break times are not provided
        }

        try {
            $breakStart = Carbon::createFromFormat('H:i:s', $this->breakStart);
            $breakEnd = Carbon::createFromFormat('H:i:s', $value);
        } catch (\Exception $e) {
            $fail('Invalid time format. Please use HH:MM:SS format.');

            return;
        }

        // Calculate duration - must be positive (end after start)
        $duration = $breakStart->diffInMinutes($breakEnd, false);

        if ($duration < $this->minDuration) {
            $fail("Break duration must be at least {$this->minDuration} minute.");
        }

        if ($duration > $this->maxDuration) {
            $fail('Break duration cannot exceed '.($this->maxDuration / 60).' hours. Please verify your break times.');
        }
    }
}
