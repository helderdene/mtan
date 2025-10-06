<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'notification_type',
        'report_type',
        'frequency',
        'filters',
        'settings',
        'enabled',
    ];

    protected $casts = [
        'filters' => 'array',
        'settings' => 'array',
        'enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get minimum severity from settings
     */
    public function getMinimumSeverity(): ?string
    {
        return $this->settings['minimum_severity'] ?? null;
    }

    /**
     * Check if notification should be sent based on severity
     */
    public function shouldNotifyForSeverity(string $severity): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $minimumSeverity = $this->getMinimumSeverity();

        if ($minimumSeverity === null) {
            return true; // No filter, notify all
        }

        $severityLevels = ['minor' => 1, 'moderate' => 2, 'major' => 3, 'critical' => 4];

        return ($severityLevels[$severity] ?? 0) >= ($severityLevels[$minimumSeverity] ?? 0);
    }
}
