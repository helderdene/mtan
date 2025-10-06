<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FailedJob extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'failed_jobs';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'connection',
        'queue',
        'payload',
        'exception',
        'failed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'failed_at' => 'datetime',
    ];

    /**
     * Scope a query to only include failed jobs for a specific queue.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $queue
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeQueue($query, string $queue)
    {
        return $query->where('queue', $queue);
    }

    /**
     * Scope a query to only include failed jobs within a date range.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|\Carbon\Carbon  $from
     * @param  string|\Carbon\Carbon  $to
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFailedBetween($query, $from, $to)
    {
        return $query->whereBetween('failed_at', [$from, $to]);
    }

    /**
     * Get the job class name from the payload.
     *
     * @return string|null
     */
    public function getJobClassAttribute(): ?string
    {
        $decodedPayload = $this->decoded_payload;

        if (! $decodedPayload) {
            return null;
        }

        // Extract job class from payload displayName or data.commandName
        if (isset($decodedPayload['displayName'])) {
            return $decodedPayload['displayName'];
        }

        if (isset($decodedPayload['data']['commandName'])) {
            return $decodedPayload['data']['commandName'];
        }

        return null;
    }

    /**
     * Get the decoded payload as an array.
     *
     * @return array|null
     */
    public function getDecodedPayloadAttribute(): ?array
    {
        try {
            $decoded = json_decode($this->attributes['payload'] ?? '', true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            return $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the job data from the payload.
     *
     * @return array|null
     */
    public function getJobDataAttribute(): ?array
    {
        $decodedPayload = $this->decoded_payload;

        if (! $decodedPayload) {
            return null;
        }

        // Extract job data
        if (isset($decodedPayload['data']['command'])) {
            // Unserialize the command object to get the job data
            try {
                $command = unserialize($decodedPayload['data']['command']);

                return get_object_vars($command);
            } catch (\Exception $e) {
                return $decodedPayload['data'] ?? null;
            }
        }

        return $decodedPayload['data'] ?? null;
    }

    /**
     * Get a human-readable failed time.
     *
     * @return string
     */
    public function getFailedTimeAgoAttribute(): string
    {
        return $this->failed_at?->diffForHumans() ?? 'Unknown';
    }
}
