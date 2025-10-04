<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Tenancy\TenantDatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    /**
     * Display a listing of tenants
     */
    public function index(Request $request): Response
    {
        $query = Tenant::query()
            ->orderBy('created_at', 'desc');

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                    ->orWhere('subdomain', 'like', "%{$search}%")
                    ->orWhere('domain', 'like', "%{$search}%");
            });
        }

        // Subscription plan filter
        if ($plan = $request->input('subscription_plan')) {
            $query->where('subscription_plan', $plan);
        }

        // Status filter
        if ($request->has('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $tenants = $query->paginate(15)->withQueryString();

        return Inertia::render('super-admin/tenants/Index', [
            'tenants' => $tenants,
            'filters' => $request->only(['search', 'subscription_plan', 'status']),
        ]);
    }

    /**
     * Show the form for creating a new tenant
     */
    public function create(): Response
    {
        return Inertia::render('super-admin/tenants/Form', [
            'subscriptionPlans' => [
                'starter' => 'Starter',
                'professional' => 'Professional',
                'enterprise' => 'Enterprise',
            ],
        ]);
    }

    /**
     * Store a newly created tenant
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'subdomain' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z0-9-]+$/',
                'unique:tenants,subdomain',
            ],
            'domain' => ['nullable', 'string', 'max:255', 'unique:tenants,domain'],
            'admin_email' => ['required', 'email', 'max:255'],
            'subscription_plan' => ['required', 'in:starter,professional,enterprise'],
            'max_employees' => ['nullable', 'integer', 'min:1'],
            'max_devices' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ], [
            'subdomain.regex' => 'Subdomain can only contain lowercase letters, numbers, and hyphens.',
        ]);

        // Set defaults based on subscription plan
        $limits = $this->getSubscriptionLimits($validated['subscription_plan']);
        $validated['max_employees'] = $validated['max_employees'] ?? $limits['max_employees'];
        $validated['max_devices'] = $validated['max_devices'] ?? $limits['max_devices'];

        // Generate unique tenant ID and database name
        $validated['id'] = 'tenant_'.Str::random(16);
        $validated['database_name'] = 'tenant_'.$validated['subdomain'];
        $validated['database_host'] = env('DB_HOST', '127.0.0.1');
        $validated['is_active'] = $validated['is_active'] ?? true;

        // Generate temporary admin password
        $validated['admin_password'] = Str::random(12);

        Tenant::create($validated);

        return redirect()
            ->route('super-admin.tenants.index')
            ->with('success', 'Tenant created successfully. Use the Provision action to create the database.');
    }

    /**
     * Display the specified tenant
     */
    public function show(string $id): Response
    {
        $tenant = Tenant::findOrFail($id);

        return Inertia::render('super-admin/tenants/Show', [
            'tenant' => $tenant,
        ]);
    }

    /**
     * Show the form for editing the specified tenant
     */
    public function edit(string $id): Response
    {
        $tenant = Tenant::findOrFail($id);

        return Inertia::render('super-admin/tenants/Form', [
            'tenant' => $tenant,
            'subscriptionPlans' => [
                'starter' => 'Starter',
                'professional' => 'Professional',
                'enterprise' => 'Enterprise',
            ],
        ]);
    }

    /**
     * Update the specified tenant
     */
    public function update(Request $request, string $id)
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'subscription_plan' => ['required', 'in:starter,professional,enterprise'],
            'max_employees' => ['required', 'integer', 'min:1'],
            'max_devices' => ['required', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        $tenant->update($validated);

        return redirect()
            ->route('super-admin.tenants.index')
            ->with('success', 'Tenant updated successfully.');
    }

    /**
     * Remove (deactivate) the specified tenant
     */
    public function destroy(string $id)
    {
        $tenant = Tenant::findOrFail($id);

        // Deactivate instead of deleting
        $tenant->update(['is_active' => false]);

        return redirect()
            ->route('super-admin.tenants.index')
            ->with('success', 'Tenant deactivated successfully.');
    }

    /**
     * Provision tenant database
     */
    public function provision(string $id, TenantDatabaseManager $manager)
    {
        $tenant = Tenant::findOrFail($id);

        try {
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

            $manager->provisionTenantDatabase($tenantDto);

            return redirect()
                ->route('super-admin.tenants.show', $tenant->id)
                ->with('success', 'Tenant database provisioned successfully.')
                ->with('admin_credentials', [
                    'email' => $tenant->admin_email,
                    'password' => $tenant->admin_password,
                ]);
        } catch (\Exception $e) {
            return redirect()
                ->route('super-admin.tenants.show', $tenant->id)
                ->with('error', 'Failed to provision tenant database: '.$e->getMessage());
        }
    }

    /**
     * Resend admin credentials email
     */
    public function sendCredentials(string $id)
    {
        $tenant = Tenant::findOrFail($id);

        if (! $tenant->admin_email || ! $tenant->admin_password) {
            return redirect()
                ->route('super-admin.tenants.show', $tenant->id)
                ->with('error', 'Admin credentials not found for this tenant.');
        }

        try {
            // Build login URL
            $baseUrl = config('app.url');
            $baseDomain = parse_url($baseUrl, PHP_URL_HOST);
            $loginUrl = $tenant->domain
                ? 'https://'.$tenant->domain.'/login'
                : 'http://'.$tenant->subdomain.'.'.$baseDomain.'/login';

            \Illuminate\Support\Facades\Mail::to($tenant->admin_email)->send(
                new \App\Mail\TenantAdminCredentialsMail(
                    companyName: $tenant->company_name,
                    email: $tenant->admin_email,
                    password: $tenant->admin_password,
                    loginUrl: $loginUrl,
                )
            );

            return redirect()
                ->route('super-admin.tenants.show', $tenant->id)
                ->with('success', 'Admin credentials sent to '.$tenant->admin_email);
        } catch (\Exception $e) {
            return redirect()
                ->route('super-admin.tenants.show', $tenant->id)
                ->with('error', 'Failed to send credentials: '.$e->getMessage());
        }
    }

    /**
     * Get subscription plan limits
     */
    protected function getSubscriptionLimits(string $plan): array
    {
        return match ($plan) {
            'starter' => ['max_employees' => 50, 'max_devices' => 5],
            'professional' => ['max_employees' => 200, 'max_devices' => 20],
            'enterprise' => ['max_employees' => 1000, 'max_devices' => 100],
            default => ['max_employees' => 50, 'max_devices' => 5],
        };
    }
}
