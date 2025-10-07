<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StrangerLogResource extends JsonResource
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
            'device_id' => $this->device_id,
            'device' => $this->whenLoaded('device', function () {
                return [
                    'id' => $this->device->id,
                    'device_id' => $this->device->device_id,
                    'name' => $this->device->name,
                    'location' => $this->device->location,
                ];
            }),
            'employee_id' => $this->employee_id,
            'employee' => new EmployeeResource($this->whenLoaded('employee')),
            'matched_by' => $this->matched_by,
            'matchedBy' => $this->whenLoaded('matchedBy', function () {
                return [
                    'id' => $this->matchedBy->id,
                    'name' => $this->matchedBy->name,
                    'email' => $this->matchedBy->email,
                ];
            }),
            'detected_at' => $this->detected_at->toIso8601String(),
            'photo_path' => $this->photo_path,
            'photo_url' => $this->when(
                $this->photo_path,
                fn () => app(\App\Services\PhotoStorageService::class)->getSignedUrl($this->photo_path)
            ),
            'match_status' => $this->match_status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
