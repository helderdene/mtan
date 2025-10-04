<?php

namespace App\Console\Commands;

use App\Jobs\SyncEmployeeToDevices;
use App\Models\Tenant as TenantModel;
use App\Models\Tenant\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncEmployeeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employee:sync
                            {employee_id? : The ID of the employee to sync}
                            {--all : Sync all active employees}
                            {--tenant= : Specify tenant ID (if not in tenant context)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync employee(s) to all active devices';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            // Get tenant ID
            $tenantId = $this->option('tenant') ?? $this->getTenantIdFromConnection();

            if (!$tenantId) {
                $this->error('Unable to determine tenant. Please specify --tenant option or run in tenant context.');
                return self::FAILURE;
            }

            // Verify tenant exists and is active
            $tenant = TenantModel::on(config('database.default'))
                ->where('id', $tenantId)
                ->where('is_active', true)
                ->first();

            if (!$tenant) {
                $this->error("Tenant '{$tenantId}' not found or inactive.");
                return self::FAILURE;
            }

            $this->info("Syncing employees for tenant: {$tenant->company_name}");

            // Sync all employees or specific employee
            if ($this->option('all')) {
                return $this->syncAllEmployees($tenantId);
            }

            $employeeId = $this->argument('employee_id');

            if (!$employeeId) {
                $this->error('Please provide an employee ID or use --all flag.');
                return self::FAILURE;
            }

            return $this->syncEmployee($employeeId, $tenantId);

        } catch (\Exception $e) {
            $this->error('Failed to sync employee(s): ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Sync a specific employee
     */
    protected function syncEmployee(int $employeeId, string $tenantId): int
    {
        // Get tenant from central database
        $tenant = TenantModel::on(config('database.default'))
            ->where('id', $tenantId)
            ->first();

        if (!$tenant) {
            $this->error("Tenant '{$tenantId}' not found.");
            return self::FAILURE;
        }

        // Setup tenant database connection
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

        app(\App\Services\Tenancy\TenantDatabaseManager::class)->setupTenantConnection($tenantDto);

        // Verify employee exists
        $employee = Employee::on('tenant')
            ->where('id', $employeeId)
            ->first();

        if (!$employee) {
            $this->error("Employee ID {$employeeId} not found.");
            return self::FAILURE;
        }

        if (!$employee->is_active) {
            $this->warn("Employee '{$employee->full_name}' is inactive. Skipping sync.");
            return self::FAILURE;
        }

        $this->info("Syncing employee: {$employee->full_name} (ID: {$employee->id})");

        // Dispatch sync job
        SyncEmployeeToDevices::dispatch($employee->id, $tenantId, 'add');

        $this->info('✓ Sync job dispatched successfully!');

        return self::SUCCESS;
    }

    /**
     * Sync all active employees
     */
    protected function syncAllEmployees(string $tenantId): int
    {
        // Get tenant from central database
        $tenant = TenantModel::on(config('database.default'))
            ->where('id', $tenantId)
            ->first();

        if (!$tenant) {
            $this->error("Tenant '{$tenantId}' not found.");
            return self::FAILURE;
        }

        // Setup tenant database connection
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

        app(\App\Services\Tenancy\TenantDatabaseManager::class)->setupTenantConnection($tenantDto);

        $employees = Employee::on('tenant')
            ->where('is_active', true)
            ->get();

        if ($employees->isEmpty()) {
            $this->warn('No active employees found to sync.');
            return self::SUCCESS;
        }

        $this->info("Found {$employees->count()} active employee(s) to sync.");

        $progressBar = $this->output->createProgressBar($employees->count());
        $progressBar->start();

        $synced = 0;
        foreach ($employees as $employee) {
            SyncEmployeeToDevices::dispatch($employee->id, $tenantId, 'add');
            $synced++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("✓ Successfully dispatched sync jobs for {$synced} employee(s)!");

        return self::SUCCESS;
    }

    /**
     * Get tenant ID from current database connection
     */
    protected function getTenantIdFromConnection(): ?string
    {
        $database = config('database.connections.tenant.database');

        if (!$database) {
            return null;
        }

        return DB::connection(config('database.default'))
            ->table('tenants')
            ->where('database_name', $database)
            ->value('id');
    }
}
