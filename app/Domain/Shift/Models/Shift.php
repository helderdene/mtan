<?php

namespace App\Domain\Shift\Models;

use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return ShiftFactory::new();
    }
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
        'grace_period_minutes',
        'early_departure_threshold_minutes',
        'overtime_threshold_minutes',
        'half_day_threshold_minutes',
        'working_days',
        'shift_type',
        'is_overnight',
        'color_code',
        'is_active',
        'description',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'working_days' => 'array',
        'is_overnight' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the overrides for this shift.
     */
    public function overrides(): HasMany
    {
        return $this->hasMany(ShiftOverride::class);
    }
}
