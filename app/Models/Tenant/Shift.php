<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Shift extends Model
{
    use HasFactory;

    protected $connection = 'tenant';

    /**
     * Get the database connection for the model.
     */
    public function getConnectionName()
    {
        // Use default connection in testing environment
        if (app()->bound('env') && app()->environment('testing')) {
            return config('database.default');
        }

        return $this->connection;
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\ShiftFactory::new();
    }

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
        'working_days',
        'is_default',
    ];

    protected $casts = [
        'working_days' => 'array',
        'is_default' => 'boolean',
    ];

    /**
     * Relationship: Shift belongs to many employees (through pivot table)
     */
    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_shifts')
            ->withPivot('effective_from', 'effective_to')
            ->withTimestamps();
    }

    /**
     * Scope: Filter default shift
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
