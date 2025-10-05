<?php

namespace App\Services\Tenancy;

use App\DTOs\Tenant;
use App\Mail\TenantAdminCredentialsMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * TenantAdminSeeder
 *
 * Creates the default admin user for a newly provisioned tenant
 */
class TenantAdminSeeder
{
    /**
     * Create the default admin user for a tenant
     */
    public function createDefaultAdmin(Tenant $tenant): ?User
    {
        try {
            // Retrieve the admin email and password from the tenant
            $tenantModel = \App\Models\Tenant::find($tenant->id);

            if (! $tenantModel || ! $tenantModel->admin_email || ! $tenantModel->admin_password) {
                logger()->warning("Cannot create admin: missing email or password for tenant {$tenant->id}");

                return null;
            }

            // Create user in the tenant database
            $user = User::on('tenant')->create([
                'name' => 'Admin',
                'email' => $tenantModel->admin_email,
                'password' => Hash::make($tenantModel->admin_password),
                'email_verified_at' => now(),
                'role' => 'tenant_admin',
                'tenant_id' => $tenant->id,
            ]);

            // Send credentials email
            $this->sendCredentialsEmail($tenantModel);

            logger()->info("Created default admin user for tenant: {$tenant->company_name}", [
                'tenant_id' => $tenant->id,
                'admin_email' => $tenantModel->admin_email,
                'user_id' => $user->id,
            ]);

            return $user;
        } catch (\Exception $e) {
            logger()->error("Failed to create default admin for tenant: {$tenant->company_name}", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenant->id,
            ]);

            return null;
        }
    }

    /**
     * Send credentials email to the admin
     */
    private function sendCredentialsEmail(\App\Models\Tenant $tenant): void
    {
        try {
            // Build login URL based on subdomain
            $loginUrl = $this->buildLoginUrl($tenant);

            Mail::to($tenant->admin_email)->send(new TenantAdminCredentialsMail(
                companyName: $tenant->company_name,
                email: $tenant->admin_email,
                password: $tenant->admin_password,
                loginUrl: $loginUrl,
            ));

            logger()->info("Sent credentials email to admin: {$tenant->admin_email}", [
                'tenant_id' => $tenant->id,
            ]);
        } catch (\Exception $e) {
            logger()->error("Failed to send credentials email for tenant: {$tenant->company_name}", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenant->id,
                'admin_email' => $tenant->admin_email,
            ]);
        }
    }

    /**
     * Build the login URL for the tenant
     */
    private function buildLoginUrl(\App\Models\Tenant $tenant): string
    {
        // Get base domain from APP_URL
        $baseUrl = config('app.url');
        $baseDomain = parse_url($baseUrl, PHP_URL_HOST);

        // If tenant has custom domain, use that
        if ($tenant->domain) {
            return 'https://'.$tenant->domain.'/login';
        }

        // Otherwise use subdomain
        return 'http://'.$tenant->subdomain.'.'.$baseDomain.'/login';
    }
}
