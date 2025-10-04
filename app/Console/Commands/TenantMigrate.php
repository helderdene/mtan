<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class TenantMigrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:migrate {tenant_id? : The tenant ID (optional, migrates all if not provided)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations for a specific tenant or all tenants';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->argument('tenant_id');

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);
            if (! $tenant) {
                $this->error("Tenant with ID {$tenantId} not found.");

                return 1;
            }
            $this->migrateTenant($tenant);
        } else {
            $tenants = Tenant::active()->get();
            if ($tenants->isEmpty()) {
                $this->warn('No active tenants found.');

                return 0;
            }

            $this->info("Running migrations for {$tenants->count()} active tenant(s)...");
            foreach ($tenants as $tenant) {
                $this->migrateTenant($tenant);
            }
        }

        $this->info('Tenant migrations completed successfully!');

        return 0;
    }

    private function migrateTenant(Tenant $tenant): void
    {
        $this->info("Migrating tenant: {$tenant->company_name} (Database: {$tenant->database_name})");

        // Configure dynamic tenant database connection
        Config::set('database.connections.tenant_dynamic', [
            'driver' => 'mysql',
            'host' => $tenant->database_host ?? env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => $tenant->database_name,
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);

        // Reconnect to ensure fresh connection
        DB::purge('tenant_dynamic');

        // Run migrations on tenant database
        Artisan::call('migrate', [
            '--database' => 'tenant_dynamic',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        $this->line(Artisan::output());
    }
}
