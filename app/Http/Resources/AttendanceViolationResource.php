<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceViolationResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'employee_name' => $this->employee->full_name,
            'attendance_record_id' => $this->attendance_record_id,
            'daily_summary_id' => $this->daily_summary_id,
            'violation_date' => $this->violation_date->toDateString(),
            'type' => $this->type,
            'severity' => $this->severity,
            'minutes_deviation' => $this->minutes_deviation,
            'metadata' => $this->metadata,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
