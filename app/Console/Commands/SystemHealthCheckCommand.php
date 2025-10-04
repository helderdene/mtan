<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\MQTT\MQTTClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class SystemHealthCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'system:health
                          {--detailed : Show detailed information for each check}
                          {--json : Output results as JSON}';

    /**
     * The console command description.
     */
    protected $description = 'Check system health status (database, MQTT, Redis, queues)';

    private array $results = [];

    private int $exitCode = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🏥 Running System Health Checks...');
        $this->newLine();

        // Run all health checks
        $this->checkPhpExtensions();
        $this->checkCentralDatabase();
        $this->checkTenantDatabases();
        $this->checkRedisConnection();
        $this->checkMQTTConnection();
        $this->checkQueueWorkers();
        $this->checkDiskSpace();
        $this->checkEnvironmentConfig();

        // Display results
        $this->newLine();
        if ($this->option('json')) {
            $this->line(json_encode($this->results, JSON_PRETTY_PRINT));
        } else {
            $this->displaySummary();
        }

        return $this->exitCode;
    }

    private function checkPhpExtensions(): void
    {
        $this->results['php_extensions'] = [
            'status' => 'checking',
            'details' => [],
        ];

        $requiredExtensions = [
            'pdo' => 'PDO',
            'pdo_mysql' => 'PDO MySQL',
            'redis' => 'Redis',
            'mbstring' => 'Multibyte String',
            'openssl' => 'OpenSSL',
            'json' => 'JSON',
            'curl' => 'cURL',
        ];

        $missing = [];
        foreach ($requiredExtensions as $ext => $name) {
            if (! extension_loaded($ext)) {
                $missing[] = $name;
            }
        }

        if (empty($missing)) {
            $this->results['php_extensions'] = [
                'status' => 'healthy',
                'message' => 'All required PHP extensions loaded',
            ];
            $this->info('✅ PHP Extensions: All required extensions loaded');
        } else {
            $this->results['php_extensions'] = [
                'status' => 'error',
                'message' => 'Missing extensions: '.implode(', ', $missing),
                'missing' => $missing,
            ];
            $this->error('❌ PHP Extensions: Missing '.implode(', ', $missing));
            $this->exitCode = 1;
        }
    }

    private function checkCentralDatabase(): void
    {
        $this->results['central_database'] = [
            'status' => 'checking',
        ];

        try {
            DB::connection('central')->getPdo();
            $tenantCount = DB::connection('central')->table('tenants')->count();
            $deviceCount = DB::connection('central')->table('device_registry')->count();

            $this->results['central_database'] = [
                'status' => 'healthy',
                'message' => "Connected ({$tenantCount} tenants, {$deviceCount} devices)",
                'tenant_count' => $tenantCount,
                'device_count' => $deviceCount,
            ];

            $this->info("✅ Central Database: Connected ({$tenantCount} tenants, {$deviceCount} devices)");
        } catch (\Exception $e) {
            $this->results['central_database'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
            $this->error('❌ Central Database: '.$e->getMessage());
            $this->exitCode = 1;
        }
    }

    private function checkTenantDatabases(): void
    {
        $this->results['tenant_databases'] = [
            'status' => 'checking',
            'details' => [],
        ];

        try {
            $tenants = Tenant::where('is_active', true)->get();

            if ($tenants->isEmpty()) {
                $this->results['tenant_databases'] = [
                    'status' => 'warning',
                    'message' => 'No active tenants found',
                ];
                $this->warn('⚠️  Tenant Databases: No active tenants found');

                return;
            }

            $healthy = 0;
            $errors = [];

            foreach ($tenants as $tenant) {
                try {
                    // Try to connect to tenant database
                    $pdo = new \PDO(
                        "mysql:host={$tenant->database_host};dbname={$tenant->database_name}",
                        env('DB_USERNAME'),
                        env('DB_PASSWORD')
                    );

                    $healthy++;

                    if ($this->option('detailed')) {
                        $this->results['tenant_databases']['details'][] = [
                            'tenant_id' => $tenant->id,
                            'company_name' => $tenant->company_name,
                            'database_name' => $tenant->database_name,
                            'status' => 'healthy',
                        ];
                    }
                } catch (\Exception $e) {
                    $errors[] = "{$tenant->company_name}: {$e->getMessage()}";

                    if ($this->option('detailed')) {
                        $this->results['tenant_databases']['details'][] = [
                            'tenant_id' => $tenant->id,
                            'company_name' => $tenant->company_name,
                            'database_name' => $tenant->database_name,
                            'status' => 'error',
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            }

            if (empty($errors)) {
                $this->results['tenant_databases'] = [
                    'status' => 'healthy',
                    'message' => "All {$healthy} tenant databases accessible",
                    'healthy_count' => $healthy,
                ];
                $this->info("✅ Tenant Databases: All {$healthy} tenant databases accessible");
            } else {
                $this->results['tenant_databases'] = [
                    'status' => 'error',
                    'message' => count($errors).' tenant database(s) inaccessible',
                    'healthy_count' => $healthy,
                    'error_count' => count($errors),
                    'errors' => $errors,
                ];
                $this->error('❌ Tenant Databases: '.count($errors).' database(s) inaccessible');
                $this->exitCode = 1;
            }
        } catch (\Exception $e) {
            $this->results['tenant_databases'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
            $this->error('❌ Tenant Databases: '.$e->getMessage());
            $this->exitCode = 1;
        }
    }

    private function checkRedisConnection(): void
    {
        $this->results['redis'] = [
            'status' => 'checking',
        ];

        try {
            Redis::ping();
            $info = Redis::info();
            $memory = $info['used_memory_human'] ?? 'Unknown';

            $this->results['redis'] = [
                'status' => 'healthy',
                'message' => "Connected (Memory: {$memory})",
                'memory_used' => $memory,
            ];

            $this->info("✅ Redis: Connected (Memory: {$memory})");
        } catch (\Exception $e) {
            $this->results['redis'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
            $this->error('❌ Redis: '.$e->getMessage());
            $this->exitCode = 1;
        }
    }

    private function checkMQTTConnection(): void
    {
        $this->results['mqtt'] = [
            'status' => 'checking',
        ];

        try {
            $mqttClient = app(MQTTClient::class);
            $health = $mqttClient->getHealthStatus();

            if (isset($health['connected']) && $health['connected']) {
                $this->results['mqtt'] = [
                    'status' => 'healthy',
                    'message' => 'Connected to broker',
                    'broker' => env('MQTT_HOST', 'localhost').':'.env('MQTT_PORT', '1883'),
                    'subscribed_topics' => count($health['subscribed_topics'] ?? []),
                ];
                $this->info('✅ MQTT Broker: Connected');
            } else {
                $this->results['mqtt'] = [
                    'status' => 'warning',
                    'message' => 'Not connected to broker',
                    'broker' => env('MQTT_HOST', 'localhost').':'.env('MQTT_PORT', '1883'),
                ];
                $this->warn('⚠️  MQTT Broker: Not connected (this is normal if mqtt:consume is not running)');
            }
        } catch (\Exception $e) {
            $this->results['mqtt'] = [
                'status' => 'warning',
                'message' => 'Could not check MQTT status: '.$e->getMessage(),
            ];
            $this->warn('⚠️  MQTT Broker: Could not check status (this is normal in test environment)');
        }
    }

    private function checkQueueWorkers(): void
    {
        $this->results['queue_workers'] = [
            'status' => 'checking',
        ];

        try {
            // Check queue sizes
            $highPrioritySize = Redis::llen('queues:attendance-high-priority');
            $defaultSize = Redis::llen('queues:attendance-default');

            $totalPending = $highPrioritySize + $defaultSize;

            if ($totalPending > 100) {
                $this->results['queue_workers'] = [
                    'status' => 'warning',
                    'message' => "{$totalPending} jobs pending (may need more workers)",
                    'high_priority' => $highPrioritySize,
                    'default' => $defaultSize,
                ];
                $this->warn("⚠️  Queue Workers: {$totalPending} jobs pending (may need more workers)");
            } else {
                $this->results['queue_workers'] = [
                    'status' => 'healthy',
                    'message' => "{$totalPending} jobs pending",
                    'high_priority' => $highPrioritySize,
                    'default' => $defaultSize,
                ];
                $this->info("✅ Queue Workers: {$totalPending} jobs pending");
            }
        } catch (\Exception $e) {
            $this->results['queue_workers'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
            $this->error('❌ Queue Workers: '.$e->getMessage());
            $this->exitCode = 1;
        }
    }

    private function checkDiskSpace(): void
    {
        $this->results['disk_space'] = [
            'status' => 'checking',
        ];

        try {
            $freeSpace = disk_free_space('/');
            $totalSpace = disk_total_space('/');
            $usedPercent = round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2);

            $freeSpaceGB = round($freeSpace / 1024 / 1024 / 1024, 2);

            if ($usedPercent > 90) {
                $this->results['disk_space'] = [
                    'status' => 'warning',
                    'message' => "{$usedPercent}% used ({$freeSpaceGB}GB free)",
                    'used_percent' => $usedPercent,
                    'free_gb' => $freeSpaceGB,
                ];
                $this->warn("⚠️  Disk Space: {$usedPercent}% used ({$freeSpaceGB}GB free)");
            } else {
                $this->results['disk_space'] = [
                    'status' => 'healthy',
                    'message' => "{$usedPercent}% used ({$freeSpaceGB}GB free)",
                    'used_percent' => $usedPercent,
                    'free_gb' => $freeSpaceGB,
                ];
                $this->info("✅ Disk Space: {$usedPercent}% used ({$freeSpaceGB}GB free)");
            }
        } catch (\Exception $e) {
            $this->results['disk_space'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
            $this->error('❌ Disk Space: '.$e->getMessage());
        }
    }

    private function checkEnvironmentConfig(): void
    {
        $this->results['environment'] = [
            'status' => 'checking',
        ];

        $required = [
            'APP_KEY' => env('APP_KEY'),
        ];

        $optional = [
            'MQTT_HOST' => env('MQTT_HOST', 'localhost'),
            'DB_HOST' => env('DB_HOST', '127.0.0.1'),
            'CENTRAL_DB_HOST' => env('CENTRAL_DB_HOST', '127.0.0.1'),
        ];

        $missing = [];
        foreach ($required as $key => $value) {
            if (empty($value)) {
                $missing[] = $key;
            }
        }

        $warnings = [];
        foreach ($optional as $key => $value) {
            if (empty(env($key))) {
                $warnings[] = "{$key} (using default: {$value})";
            }
        }

        if (empty($missing)) {
            $message = 'All required environment variables set';
            if (! empty($warnings)) {
                $message .= ' (some using defaults)';
            }

            $this->results['environment'] = [
                'status' => 'healthy',
                'message' => $message,
                'app_env' => env('APP_ENV'),
                'app_debug' => env('APP_DEBUG') ? 'enabled' : 'disabled',
                'warnings' => $warnings,
            ];
            $this->info('✅ Environment: '.$message);
        } else {
            $this->results['environment'] = [
                'status' => 'error',
                'message' => 'Missing variables: '.implode(', ', $missing),
                'missing' => $missing,
            ];
            $this->error('❌ Environment: Missing '.implode(', ', $missing));
            $this->exitCode = 1;
        }
    }

    private function displaySummary(): void
    {
        $healthy = 0;
        $warnings = 0;
        $errors = 0;

        foreach ($this->results as $check) {
            match ($check['status']) {
                'healthy' => $healthy++,
                'warning' => $warnings++,
                'error' => $errors++,
                default => null,
            };
        }

        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 Summary:');
        $this->info("   ✅ Healthy: {$healthy}");
        if ($warnings > 0) {
            $this->warn("   ⚠️  Warnings: {$warnings}");
        }
        if ($errors > 0) {
            $this->error("   ❌ Errors: {$errors}");
        }

        if ($errors === 0 && $warnings === 0) {
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('🎉 All systems operational!');
        } else {
            $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        }
    }
}
