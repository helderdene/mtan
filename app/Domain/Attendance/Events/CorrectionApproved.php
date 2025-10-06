<?php

namespace App\Domain\Attendance\Events;

use App\Domain\Attendance\Models\AttendanceCorrection;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CorrectionApproved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AttendanceCorrection $correction
    ) {
    }
}
