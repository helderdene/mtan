<?php

namespace App\Console\Commands\Tenancy;

use App\Models\Tenant;
use Illuminate\Console\Command;

class TenantListCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:list
                            {--active : Show only active tenants}
                            {--inactive : Show only inactive tenants}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all tenants in the system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $query = Tenant::query();

        // Apply filters
        if ($this->option('active')) {
            $query->where('is_active', true);
        } elseif ($this->option('inactive')) {
            $query->where('is_active', false);
        }

        $tenants = $query->orderBy('created_at', 'desc')->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found');

            return 0;
        }

        $this->info('Tenants:');
        $this->newLine();

        $rows = $tenants->map(function ($tenant) {
            return [
                $tenant->id,
                $tenant->company_name,
                $tenant->subdomain,
                $tenant->domain ?? '-',
                $tenant->subscription_plan,
                $tenant->max_employees,
                $tenant->max_devices,
                $tenant->is_active ? '✓' : '✗',
                $tenant->created_at->format('Y-m-d H:i'),
            ];
        })->toArray();

        $this->table(
            ['ID', 'Company', 'Subdomain', 'Domain', 'Plan', 'Max Employees', 'Max Devices', 'Active', 'Created'],
            $rows
        );

        $this->newLine();
        $this->comment("Total: {$tenants->count()} tenant(s)");

        return 0;
    }
}
