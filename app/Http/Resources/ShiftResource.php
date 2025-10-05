<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'break_start' => $this->break_start,
            'break_end' => $this->break_end,
            'working_days' => $this->working_days,
            'is_default' => $this->is_default,
            'employees_count' => $this->whenCounted('employees'),
            'break_duration_minutes' => $this->getBreakDurationMinutes(),
            'is_overnight' => $this->isOvernightShift(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Calculate break duration in minutes
     *
     * @return int|null
     */
    private function getBreakDurationMinutes(): ?int
    {
        if (!$this->break_start || !$this->break_end) {
            return null;
        }

        $breakStart = Carbon::parse($this->break_start);
        $breakEnd = Carbon::parse($this->break_end);

        return $breakStart->diffInMinutes($breakEnd);
    }

    /**
     * Check if this is an overnight shift
     *
     * @return bool
     */
    private function isOvernightShift(): bool
    {
        if (!$this->start_time || !$this->end_time) {
            return false;
        }

        $startTime = Carbon::parse($this->start_time);
        $endTime = Carbon::parse($this->end_time);

        return $endTime->lessThan($startTime);
    }
}
