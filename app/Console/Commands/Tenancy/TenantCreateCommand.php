<?php

namespace App\Console\Commands\Tenancy;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TenantCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:create
                            {--company= : Company name}
                            {--subdomain= : Subdomain}
                            {--domain= : Custom domain (optional)}
                            {--plan=professional : Subscription plan (free, professional, enterprise)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new tenant in the central database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get input values
        $companyName = $this->option('company') ?? $this->ask('Company name');
        $subdomain = $this->option('subdomain') ?? $this->ask('Subdomain');
        $domain = $this->option('domain');
        $plan = $this->option('plan');

        // Validate subdomain
        if (! preg_match('/^[a-z0-9-]+$/', $subdomain)) {
            $this->error('Subdomain must contain only lowercase letters, numbers, and hyphens');

            return 1;
        }

        // Check if subdomain already exists
        if (Tenant::where('subdomain', $subdomain)->exists()) {
            $this->error("Tenant with subdomain '{$subdomain}' already exists");

            return 1;
        }

        // Check if domain already exists (if provided)
        if ($domain && Tenant::where('domain', $domain)->exists()) {
            $this->error("Tenant with domain '{$domain}' already exists");

            return 1;
        }

        // Determine plan limits
        $limits = $this->getPlanLimits($plan);

        // Generate tenant ID
        $tenantId = 'tenant_' . Str::uuid();

        // Create tenant
        $tenant = Tenant::create([
            'id' => $tenantId,
            'company_name' => $companyName,
            'subdomain' => $subdomain,
            'domain' => $domain,
            'database_name' => 'tenant_' . str_replace('-', '_', $subdomain),
            'database_host' => env('DB_HOST', '127.0.0.1'),
            'subscription_plan' => $plan,
            'max_employees' => $limits['max_employees'],
            'max_devices' => $limits['max_devices'],
            'is_active' => true,
        ]);

        $this->info('Tenant created successfully!');
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $tenant->id],
                ['Company', $tenant->company_name],
                ['Subdomain', $tenant->subdomain],
                ['Domain', $tenant->domain ?? 'N/A'],
                ['Database', $tenant->database_name],
                ['Plan', $tenant->subscription_plan],
                ['Max Employees', $tenant->max_employees],
                ['Max Devices', $tenant->max_devices],
                ['Status', $tenant->is_active ? 'Active' : 'Inactive'],
            ]
        );

        $this->newLine();
        $this->comment('Next step: Run "php artisan tenant:provision ' . $tenant->id . '" to provision the tenant database');

        return 0;
    }

    /**
     * Get plan limits based on subscription plan
     */
    private function getPlanLimits(string $plan): array
    {
        return match ($plan) {
            'free' => ['max_employees' => 10, 'max_devices' => 1],
            'professional' => ['max_employees' => 100, 'max_devices' => 10],
            'enterprise' => ['max_employees' => 1000, 'max_devices' => 50],
            default => ['max_employees' => 100, 'max_devices' => 10],
        };
    }
}
