<?php

namespace App\Domain\Shift\Models;

use Database\Factories\ShiftOverrideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftOverride extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return ShiftOverrideFactory::new();
    }
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'shift_id',
        'employee_id',
        'override_date',
        'type',
        'custom_start_time',
        'custom_end_time',
        'reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'override_date' => 'date',
    ];

    /**
     * Get the shift this override applies to.
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Get the employee this override applies to.
     * Null means company-wide override.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Check if this is a company-wide override.
     */
    public function isCompanyWide(): bool
    {
        return $this->employee_id === null;
    }

    /**
     * Check if this is an employee-specific override.
     */
    public function isEmployeeSpecific(): bool
    {
        return $this->employee_id !== null;
    }

    /**
     * Check if work is required on this override.
     */
    public function isWorkRequired(): bool
    {
        return !in_array($this->type, ['holiday', 'off-day']);
    }

    /**
     * Check if this override has custom times.
     */
    public function hasCustomTimes(): bool
    {
        return in_array($this->type, ['half-day', 'custom-shift'])
            && $this->custom_start_time !== null
            && $this->custom_end_time !== null;
    }
}
