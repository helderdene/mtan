<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailyAttendanceSummaryResource extends JsonResource
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
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'date' => $this->date->toDateString(),
            'first_check_in' => $this->first_check_in,
            'last_check_out' => $this->last_check_out,
            'total_work_minutes' => $this->total_work_minutes,
            'total_work_hours' => $this->total_work_hours,
            'total_break_minutes' => $this->total_break_minutes,
            'total_break_hours' => $this->total_break_hours,
            'overtime_minutes' => $this->overtime_minutes,
            'overtime_hours' => $this->overtime_hours,
            'status' => $this->status,
            'is_complete' => $this->is_complete,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
