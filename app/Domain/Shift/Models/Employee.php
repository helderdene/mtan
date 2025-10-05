<?php

namespace App\Domain\Shift\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return EmployeeFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_code',
        'custom_id',
        'name',
        'email',
        'phone',
        'department_id',
        'designation',
        'employee_type',
        'card_number',
        'joining_date',
        'leaving_date',
        'reporting_manager_id',
        'is_active',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'joining_date' => 'date',
        'leaving_date' => 'date',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the overrides for this employee.
     */
    public function overrides(): HasMany
    {
        return $this->hasMany(ShiftOverride::class);
    }
}
