<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BreakWithinShiftHours implements ValidationRule
{
    protected string|null $shiftStart;
    protected string|null $shiftEnd;

    public function __construct(?string $shiftStart, ?string $shiftEnd)
    {
        $this->shiftStart = $shiftStart;
        $this->shiftEnd = $shiftEnd;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value || ! $this->shiftStart || ! $this->shiftEnd) {
            return; // Skip if any required value is missing
        }

        try {
            $breakTime = Carbon::createFromFormat('H:i:s', $value);
            $shiftStart = Carbon::createFromFormat('H:i:s', $this->shiftStart);
            $shiftEnd = Carbon::createFromFormat('H:i:s', $this->shiftEnd);
        } catch (\Exception $e) {
            $fail('Invalid time format. Please use HH:MM:SS format.');

            return;
        }

        $isOvernightShift = $shiftEnd->lessThan($shiftStart);

        if ($isOvernightShift) {
            // For overnight shifts, break must be within shift hours
            // and cannot span midnight (simplified)
            $isValid = (
                $breakTime->greaterThanOrEqualTo($shiftStart) ||
                $breakTime->lessThanOrEqualTo($shiftEnd)
            );

            if (! $isValid) {
                $fail('Break times must fall within shift working hours. For overnight shifts, breaks cannot span across midnight.');
            }
        } else {
            // For standard shifts, break must be between start and end
            if ($breakTime->lessThan($shiftStart) || $breakTime->greaterThan($shiftEnd)) {
                $fail("Break times must fall within shift working hours ({$this->shiftStart} - {$this->shiftEnd}).");
            }
        }
    }
}
