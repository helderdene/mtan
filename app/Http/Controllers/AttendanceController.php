<?php

namespace App\Http\Controllers;

use App\Models\Tenant\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /**
     * Display the live attendance feed
     */
    public function liveFeed(): Response
    {
        // Get latest 50 attendance records with relationships
        $records = AttendanceRecord::on('tenant')
            ->with(['employee', 'device'])
            ->orderBy('recorded_at', 'desc')
            ->limit(50)
            ->get();

        // Calculate statistics
        $today = Carbon::today();
        $oneHourAgo = Carbon::now()->subHour();

        $stats = [
            'today_total' => AttendanceRecord::on('tenant')
                ->whereDate('recorded_at', $today)
                ->count(),
            'last_hour' => AttendanceRecord::on('tenant')
                ->where('recorded_at', '>=', $oneHourAgo)
                ->count(),
            'unique_employees' => AttendanceRecord::on('tenant')
                ->whereDate('recorded_at', $today)
                ->distinct('employee_id')
                ->count('employee_id'),
        ];

        return Inertia::render('attendance/LiveFeed', [
            'records' => $records,
            'stats' => $stats,
        ]);
    }

    /**
     * Get attendance notifications from cache
     */
    public function getAttendanceNotifications(Request $request): JsonResponse
    {
        // Get tenant from request attributes (set by InitializeTenancy middleware)
        $tenant = $request->attributes->get('tenant');

        if (!$tenant) {
            return response()->json(['notifications' => []]);
        }

        // For database cache, we need to query the cache table directly
        // Cache keys have a prefix (e.g., "attendguard_cache_")
        $notifications = [];

        // Get cache prefix from config
        $prefix = config('cache.prefix');

        // Query the cache entries table for this tenant's notifications
        $cacheEntries = \DB::table('cache')
            ->where('key', 'like', $prefix . 'attendance_notification:' . $tenant->id . ':%')
            ->get();

        foreach ($cacheEntries as $entry) {
            $notification = unserialize($entry->value);

            if ($notification) {
                $notifications[] = $notification;

                // Delete the notification after reading
                // Remove the prefix to get the actual cache key
                $cacheKey = str_replace($prefix, '', $entry->key);
                \Cache::forget($cacheKey);
            }
        }

        return response()->json(['notifications' => $notifications]);
    }
}
