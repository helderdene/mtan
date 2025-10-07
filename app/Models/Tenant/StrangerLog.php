<?php

namespace App\Models\Tenant;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StrangerLog extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\StrangerLogFactory::new();
    }

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
        'device_id',
        'employee_id',
        'matched_by',
        'detected_at',
        'photo_path',
        'match_status',
        'notes',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
    ];

    /**
     * Get the device that detected the stranger.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Get the employee this stranger was matched to (if matched).
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who matched this stranger to an employee.
     */
    public function matchedBy(): BelongsTo
    {
        return $this->setConnection(config('database.default'))
            ->belongsTo(User::class, 'matched_by');
    }
}
