<?php

namespace App\Console\Commands;

use App\Domain\Attendance\Models\AttendanceViolation;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\DailyViolationDigest;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendDailyViolationDigestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-daily-violation-digest {--date= : The date to generate digest for (default: yesterday)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily violation digest emails to managers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Get the date to process (default: yesterday)
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $this->info("Generating daily violation digest for {$date->toDateString()}...");

        // Get all managers with digest preferences enabled
        $managers = User::whereHas('notificationPreferences', function ($query) {
            $query->where('notification_type', 'violation_digest')
                ->where('enabled', true);
        })->get();

        if ($managers->isEmpty()) {
            $this->warn('No managers with digest preferences enabled.');

            return self::SUCCESS;
        }

        $this->info("Found {$managers->count()} managers with digest enabled.");

        $totalSent = 0;

        foreach ($managers as $manager) {
            // Get violations for this manager's team on the specified date
            $violations = AttendanceViolation::whereHas('employee', function ($query) use ($manager) {
                $query->where('manager_id', $manager->id);
            })
                ->whereDate('violation_date', $date)
                ->with('employee')
                ->orderBy('severity')
                ->orderBy('created_at', 'desc')
                ->get();

            // Skip if no violations
            if ($violations->isEmpty()) {
                $this->line("  Skipping {$manager->name} - no violations for their team");

                continue;
            }

            // Get preference for severity filtering
            $preference = NotificationPreference::where('user_id', $manager->id)
                ->where('notification_type', 'violation_digest')
                ->first();

            // Filter violations by minimum severity if configured
            if ($preference && $preference->getMinimumSeverity()) {
                $minimumSeverity = $preference->getMinimumSeverity();
                $severityLevels = ['minor' => 1, 'moderate' => 2, 'major' => 3, 'critical' => 4];

                $violations = $violations->filter(function ($violation) use ($minimumSeverity, $severityLevels) {
                    return ($severityLevels[$violation->severity] ?? 0) >= ($severityLevels[$minimumSeverity] ?? 0);
                });

                if ($violations->isEmpty()) {
                    $this->line("  Skipping {$manager->name} - no violations meet severity threshold");

                    continue;
                }
            }

            // Send digest notification
            $manager->notify(new DailyViolationDigest($violations, $date));

            $totalSent++;
            $this->line("  ✓ Sent digest to {$manager->name} ({$violations->count()} violations)");
        }

        $this->info("✓ Digest sent to {$totalSent} manager(s)");

        return self::SUCCESS;
    }
}
