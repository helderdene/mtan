<?php

namespace App\Events;

use App\Models\Tenant\Device;
use App\Models\Tenant\Employee;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeDeviceSyncCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Employee $employee,
        public Device $device,
        public string $status,
        public string $operator
    ) {}

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'employee_id' => $this->employee->id,
            'employee_name' => $this->employee->full_name,
            'device_id' => $this->device->id,
            'device_name' => $this->device->device_name ?: $this->device->device_id,
            'status' => $this->status,
            'operator' => $this->operator,
            'message' => $this->status === 'synced'
                ? "Successfully synced {$this->employee->full_name} to device {$this->device->device_name}"
                : "Failed to sync {$this->employee->full_name} to device {$this->device->device_name}",
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('employee.'.$this->employee->id),
        ];
    }
}
