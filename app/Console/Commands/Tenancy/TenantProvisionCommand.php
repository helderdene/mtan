<?php

namespace App\Console\Commands\Tenancy;

use App\Models\Tenant;
use App\Services\Tenancy\TenantDatabaseManager;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TenantProvisionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:provision
                            {tenant_id : The tenant ID to provision}
                            {--seed : Run database seeder after migrations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision a tenant database (create database, run migrations, and optionally seed)';

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

        $this->info("Provisioning tenant: {$tenant->company_name}");
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

        // Provision tenant database
        $this->newLine();
        $this->line('Creating database...');

        if (! $manager->provisionTenant($tenantDto)) {
            $this->error('Failed to provision tenant database');

            return 1;
        }

        $this->info('✓ Database created');
        $this->info('✓ Migrations executed');

        // Run seeder if requested
        if ($this->option('seed')) {
            $this->newLine();
            $this->line('Running database seeder...');

            $seeder = new TenantDatabaseSeeder();

            // Set default connection to tenant
            $originalConnection = DB::getDefaultConnection();
            DB::setDefaultConnection('tenant');

            $seeder->run();

            // Restore original connection
            DB::setDefaultConnection($originalConnection);

            $this->info('✓ Database seeded');
        }

        $this->newLine();
        $this->info('Tenant provisioned successfully!');
        $this->comment("Access URL: https://{$tenant->subdomain}." . config('app.domain', 'example.com'));

        return 0;
    }
}
