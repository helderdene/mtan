<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftRotationPattern extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'cycle_type',
        'rotation_sequence',
        'description',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rotation_sequence' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get all employee rotations using this pattern.
     */
    public function employeeRotations(): HasMany
    {
        return $this->hasMany(EmployeeShiftRotation::class, 'rotation_pattern_id');
    }

    /**
     * Get the number of shifts in this rotation.
     */
    public function getRotationLengthAttribute(): int
    {
        return count($this->rotation_sequence);
    }

    /**
     * Scope a query to only include active patterns.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to filter by cycle type.
     */
    public function scopeOfCycleType($query, string $cycleType)
    {
        return $query->where('cycle_type', $cycleType);
    }
}
