<?php

namespace App\Events;

use App\Domain\Attendance\Models\AttendanceViolation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ViolationDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public AttendanceViolation $violation
    ) {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('violations.'.$this->violation->employee_id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->violation->id,
            'type' => $this->violation->type,
            'severity' => $this->violation->severity,
            'employee_id' => $this->violation->employee_id,
            'violation_date' => $this->violation->violation_date->toDateString(),
            'minutes_deviation' => $this->violation->minutes_deviation,
        ];
    }
}
