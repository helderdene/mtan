<?php

namespace App\Models;

use App\Models\Tenant\Employee;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeShiftRotation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'rotation_pattern_id',
        'start_date',
        'current_position',
        'last_rotated_at',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'last_rotated_at' => 'date',
        'is_active' => 'boolean',
        'current_position' => 'integer',
    ];

    /**
     * Get the employee that owns this rotation.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the rotation pattern.
     */
    public function rotationPattern(): BelongsTo
    {
        return $this->belongsTo(ShiftRotationPattern::class, 'rotation_pattern_id');
    }

    /**
     * Get the current shift ID based on current position.
     */
    public function getCurrentShiftIdAttribute(): ?int
    {
        $pattern = $this->rotationPattern;

        if (!$pattern || empty($pattern->rotation_sequence)) {
            return null;
        }

        return $pattern->rotation_sequence[$this->current_position] ?? null;
    }

    /**
     * Advance to the next position in rotation.
     */
    public function advanceRotation(): void
    {
        $pattern = $this->rotationPattern;

        if (!$pattern) {
            return;
        }

        $this->current_position = ($this->current_position + 1) % count($pattern->rotation_sequence);
        $this->last_rotated_at = now();
        $this->save();
    }

    /**
     * Scope a query to only include active rotations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by employee.
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}
