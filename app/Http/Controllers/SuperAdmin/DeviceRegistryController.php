<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\DeviceRegistry;
use App\Models\Tenant;
use Illuminate\Http\Request;

class DeviceRegistryController extends Controller
{
    /**
     * Display a listing of device registrations
     */
    public function index(Request $request)
    {
        $query = DeviceRegistry::with('tenant')->orderBy('created_at', 'desc');

        // Filter by tenant if specified
        if ($request->has('tenant_id') && $request->tenant_id) {
            $query->where('tenant_id', $request->tenant_id);
        }

        // Filter by active status if specified
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by device_id or device_name
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('device_id', 'like', "%{$search}%")
                    ->orWhere('device_name', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $devices = $query->paginate(15);

        return inertia('super-admin/device-registry/Index', [
            'devices' => $devices,
            'tenants' => Tenant::select('id', 'company_name')->orderBy('company_name')->get(),
            'filters' => [
                'tenant_id' => $request->tenant_id,
                'is_active' => $request->is_active,
                'search' => $request->search,
            ],
        ]);
    }

    /**
     * Show the form for creating a new device registration
     */
    public function create()
    {
        return inertia('super-admin/device-registry/Form', [
            'tenants' => Tenant::select('id', 'company_name')
                ->where('is_active', true)
                ->orderBy('company_name')
                ->get(),
        ]);
    }

    /**
     * Store a newly created device registration
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],
            'device_id' => ['required', 'string', 'max:100', 'unique:device_registry,device_id'],
            'device_name' => ['required', 'string', 'max:255'],
            'device_type' => ['required', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
            'mac_address' => ['nullable', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/'],
            'firmware_version' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'metadata' => ['nullable', 'json'],
        ]);

        DeviceRegistry::create($validated);

        return redirect()->route('super-admin.device-registry.index')
            ->with('success', 'Device registered successfully.');
    }

    /**
     * Display the specified device registration
     */
    public function show(string $id)
    {
        $device = DeviceRegistry::with('tenant')->findOrFail($id);

        return inertia('super-admin/device-registry/Show', [
            'device' => $device,
        ]);
    }

    /**
     * Show the form for editing the specified device registration
     */
    public function edit(string $id)
    {
        $device = DeviceRegistry::findOrFail($id);

        return inertia('super-admin/device-registry/Form', [
            'device' => $device,
            'tenants' => Tenant::select('id', 'company_name')
                ->where('is_active', true)
                ->orderBy('company_name')
                ->get(),
        ]);
    }

    /**
     * Update the specified device registration
     */
    public function update(Request $request, string $id)
    {
        $device = DeviceRegistry::findOrFail($id);

        $validated = $request->validate([
            'device_name' => ['required', 'string', 'max:255'],
            'device_type' => ['required', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
            'mac_address' => ['nullable', 'regex:/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/'],
            'firmware_version' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            'metadata' => ['nullable', 'json'],
        ]);

        // Do not allow updating tenant_id or device_id for security
        $device->update($validated);

        return redirect()->route('super-admin.device-registry.index')
            ->with('success', 'Device updated successfully.');
    }

    /**
     * Remove the specified device registration
     */
    public function destroy(string $id)
    {
        $device = DeviceRegistry::findOrFail($id);
        $device->delete();

        return redirect()->route('super-admin.device-registry.index')
            ->with('success', 'Device deleted successfully.');
    }
}
