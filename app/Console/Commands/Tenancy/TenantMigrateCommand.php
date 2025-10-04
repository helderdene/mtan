<?php

namespace App\Console\Commands\Tenancy;

use App\Models\Tenant;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Console\Command;

class TenantMigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:migrate
                            {tenant_id : The tenant ID to run migrations for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations on a tenant database';

    /**
     * Execute the console command.
     */
    public function handle(TenantDatabaseManager $manager)
    {
        $tenantId = $this->argument('tenant_id');

        // Find tenant
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant with ID '{$tenantId}' not found");

            return 1;
        }

        $this->info("Running migrations for tenant: {$tenant->company_name}");
        $this->info("Database: {$tenant->database_name}");

        // Convert Eloquent model to DTO
        $tenantDto = new \App\DTOs\Tenant(
            id: $tenant->id,
            company_name: $tenant->company_name,
            subdomain: $tenant->subdomain,
            domain: $tenant->domain,
            database_name: $tenant->database_name,
            database_host: $tenant->database_host,
            subscription_plan: $tenant->subscription_plan,
            max_employees: $tenant->max_employees,
            max_devices: $tenant->max_devices,
            is_active: $tenant->is_active,
        );

        $this->newLine();

        if (! $manager->runMigrations($tenantDto)) {
            $this->error('Failed to run migrations');

            return 1;
        }

        $this->newLine();
        $this->info('✓ Migrations executed successfully!');

        return 0;
    }
}
