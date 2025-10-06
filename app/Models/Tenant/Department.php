<?php

namespace App\Models\Tenant;

use App\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory, UsesTenantConnection;

    protected $connection = 'tenant';

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Relationship: Department has many employees
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Create a new factory instance for the model
     */
    protected static function newFactory()
    {
        return \Database\Factories\DepartmentFactory::new();
    }
}
