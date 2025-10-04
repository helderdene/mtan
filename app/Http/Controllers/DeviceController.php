<?php

namespace App\Http\Controllers;

use App\Models\Tenant\Device;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeviceController extends Controller
{
    /**
     * Display a listing of devices assigned to tenant
     */
    public function index(): Response
    {
        $devices = Device::query()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($device) {
                return [
                    'id' => $device->id,
                    'device_id' => $device->device_id,
                    'name' => $device->name,
                    'location' => $device->location,
                    'device_type' => $device->device_type,
                    'ip_address' => $device->ip_address,
                    'mac_address' => $device->mac_address,
                    'firmware_version' => $device->firmware_version,
                    'capacity' => $device->capacity,
                    'current_count' => $device->current_count,
                    'is_active' => $device->is_active,
                    'status' => $device->status,
                    'last_heartbeat_at' => $device->last_heartbeat_at?->toIso8601String(),
                    'last_sync_at' => $device->last_sync_at?->toIso8601String(),
                    'attendance_records_count' => 0, // TODO: Implement when AttendanceRecord is ready
                ];
            });

        return Inertia::render('devices/Index', [
            'devices' => $devices,
        ]);
    }

    /**
     * Display the specified device
     */
    public function show(Device $device): Response
    {
        return Inertia::render('devices/Show', [
            'device' => [
                'id' => $device->id,
                'device_id' => $device->device_id,
                'name' => $device->name,
                'location' => $device->location,
                'device_type' => $device->device_type,
                'ip_address' => $device->ip_address,
                'mac_address' => $device->mac_address,
                'firmware_version' => $device->firmware_version,
                'capacity' => $device->capacity,
                'current_count' => $device->current_count,
                'is_entry_device' => $device->is_entry_device,
                'is_exit_device' => $device->is_exit_device,
                'timezone' => $device->timezone,
                'settings' => $device->settings,
                'is_active' => $device->is_active,
                'status' => $device->status,
                'last_heartbeat_at' => $device->last_heartbeat_at?->toIso8601String(),
                'last_sync_at' => $device->last_sync_at?->toIso8601String(),
                'created_at' => $device->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Show the form for editing device settings
     */
    public function edit(Device $device): Response
    {
        return Inertia::render('devices/Edit', [
            'device' => [
                'id' => $device->id,
                'device_id' => $device->device_id,
                'name' => $device->name,
                'location' => $device->location,
                'device_type' => $device->device_type,
                'ip_address' => $device->ip_address,
                'timezone' => $device->timezone,
                'is_entry_device' => $device->is_entry_device,
                'is_exit_device' => $device->is_exit_device,
                'is_active' => $device->is_active,
            ],
        ]);
    }

    /**
     * Update device settings (tenant can only modify settings, not create)
     */
    public function update(Request $request, Device $device)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
            'timezone' => ['required', 'string', 'max:50'],
            'is_entry_device' => ['boolean'],
            'is_exit_device' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $device->update($validated);

        return redirect()
            ->route('devices.index')
            ->with('success', 'Device settings updated successfully.');
    }

    /**
     * Get device status
     */
    public function status(Device $device)
    {
        return response()->json([
            'device_id' => $device->device_id,
            'name' => $device->name,
            'status' => $device->status,
            'is_online' => $device->isOnline(),
            'last_heartbeat_at' => $device->last_heartbeat_at?->toIso8601String(),
            'last_sync_at' => $device->last_sync_at?->toIso8601String(),
            'current_count' => $device->current_count,
            'capacity' => $device->capacity,
        ]);
    }
}
