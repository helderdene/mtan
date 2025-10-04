<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\DeviceRegistry;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the super admin dashboard with system metrics
     */
    public function index(): Response
    {
        $metrics = $this->getSystemMetrics();

        return Inertia::render('super-admin/Dashboard', [
            'metrics' => $metrics,
        ]);
    }

    /**
     * Get system-wide metrics for the dashboard
     */
    protected function getSystemMetrics(): array
    {
        return [
            'tenants' => [
                'total' => Tenant::count(),
                'active' => Tenant::where('is_active', true)->count(),
                'inactive' => Tenant::where('is_active', false)->count(),
            ],
            'devices' => [
                'total' => DeviceRegistry::count(),
                'active' => DeviceRegistry::where('is_active', true)->count(),
                'inactive' => DeviceRegistry::where('is_active', false)->count(),
            ],
            'subscription_breakdown' => Tenant::select('subscription_plan', DB::raw('count(*) as count'))
                ->groupBy('subscription_plan')
                ->get()
                ->mapWithKeys(fn ($item) => [$item->subscription_plan => $item->count])
                ->toArray(),
            'recent_tenants' => Tenant::latest()
                ->take(5)
                ->get(['id', 'company_name', 'subdomain', 'subscription_plan', 'is_active', 'created_at'])
                ->toArray(),
        ];
    }
}
