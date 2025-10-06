<?php

namespace App\Models\Tenant;

use App\Jobs\SyncEmployeeToDevices;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class Employee extends Model
{
    use HasFactory, Notifiable;

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

    protected $fillable = [
        'custom_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'avatar',
        'department_id',
        'manager_id',
        'is_active',
        'hired_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'hired_at' => 'datetime',
    ];

    protected $appends = [
        'full_name',
        'avatar_url',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-generate custom_id if not provided
        static::creating(function ($employee) {
            if (empty($employee->custom_id)) {
                $employee->custom_id = static::generateCustomId();
            }
        });

        // Skip device sync in testing environment
        if (app()->bound('env') && app()->environment('testing')) {
            return;
        }

        // Sync employee to devices after creation
        static::created(function ($employee) {
            if ($employee->is_active) {
                static::dispatchSyncJob($employee, 'created');
            }
        });

        // Sync employee to devices after update
        static::updated(function ($employee) {
            if ($employee->wasChanged(['first_name', 'last_name', 'email', 'phone', 'custom_id', 'is_active'])) {
                static::dispatchSyncJob($employee, 'updated');
            }
        });

        // Remove employee from devices before deletion
        static::deleting(function ($employee) {
            static::dispatchSyncJob($employee, 'deleted');
        });
    }

    /**
     * Generate a unique custom_id for the employee
     */
    public static function generateCustomId(): string
    {
        do {
            // Generate custom_id: EMP + 6 random digits
            $customId = 'EMP'.str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);

            // Check if it already exists
            $exists = static::where('custom_id', $customId)->exists();
        } while ($exists);

        return $customId;
    }

    /**
     * Get the employee's full name
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Get the employee's avatar URL
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar) {
            return \Storage::disk('public')->url($this->avatar);
        }

        return null;
    }

    /**
     * Relationship: Employee belongs to a department
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Relationship: Employee belongs to a manager (User)
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'manager_id');
    }

    /**
     * Relationship: Employee has many shifts (through pivot table)
     */
    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(Shift::class, 'employee_shifts')
            ->withPivot('effective_from', 'effective_to')
            ->withTimestamps();
    }

    /**
     * Relationship: Get employee's current shift
     */
    public function getCurrentShiftAttribute(): ?Shift
    {
        $today = now()->toDateString();

        return $this->shifts()
            ->wherePivot('effective_from', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->where('employee_shifts.effective_to', '>=', $today)
                    ->orWhereNull('employee_shifts.effective_to');
            })
            ->first();
    }

    /**
     * Get employee's shift for a specific date.
     */
    public function getShiftForDate(\Carbon\Carbon $date): ?Shift
    {
        $dateString = $date->toDateString();

        return $this->shifts()
            ->wherePivot('effective_from', '<=', $dateString)
            ->where(function ($query) use ($dateString) {
                $query->where('employee_shifts.effective_to', '>=', $dateString)
                    ->orWhereNull('employee_shifts.effective_to');
            })
            ->first();
    }

    /**
     * Relationship: Employee has many device enrollments
     */
    public function deviceEnrollments(): HasMany
    {
        return $this->hasMany(DeviceEnrollment::class);
    }

    /**
     * Relationship: Employee has many attendance records
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * Relationship: Employee has many daily attendance summaries
     */
    public function dailySummaries(): HasMany
    {
        return $this->hasMany(\App\Domain\Attendance\Models\DailyAttendanceSummary::class);
    }

    /**
     * Scope: Filter active employees
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter employees by department
     */
    public function scopeByDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Dispatch sync job to devices
     */
    protected static function dispatchSyncJob(Employee $employee, string $action): void
    {
        // Get tenant_id from central database using the current tenant connection
        $tenantId = DB::connection(config('database.default'))
            ->table('tenants')
            ->where('database_name', config('database.connections.tenant.database'))
            ->value('id');

        if (! $tenantId) {
            \Log::warning('Cannot sync employee: tenant ID not found', [
                'employee_id' => $employee->id,
                'database' => config('database.connections.tenant.database'),
            ]);

            return;
        }

        // Dispatch appropriate sync job based on action
        match ($action) {
            'created' => SyncEmployeeToDevices::dispatch($employee->id, $tenantId, 'add'),
            'updated' => SyncEmployeeToDevices::dispatch($employee->id, $tenantId, 'edit'),
            'deleted' => \App\Jobs\RemoveEmployeeFromDevices::dispatch($employee->id, $tenantId),
            default => null,
        };
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\EmployeeFactory::new();
    }
}
