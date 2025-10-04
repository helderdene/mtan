<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ImpersonationLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    /**
     * Start impersonating a tenant user
     */
    public function start(Request $request, Tenant $tenant, User $user): RedirectResponse
    {
        // Verify super admin is active
        $superAdmin = Auth::guard('super-admin')->user();
        if (! $superAdmin || ! $superAdmin->is_active) {
            Auth::guard('super-admin')->logout();

            return redirect()->route('super-admin.login')
                ->with('error', 'Your account has been deactivated.');
        }

        // Verify tenant is active
        if (! $tenant->is_active) {
            abort(403, 'Cannot impersonate users of inactive tenant');
        }

        // Create audit log entry
        $impersonationLog = ImpersonationLog::create([
            'super_admin_id' => Auth::guard('super-admin')->id(),
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'user_email' => $user->email,
            'started_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Store impersonation state in session
        session([
            'impersonating' => [
                'super_admin_id' => Auth::guard('super-admin')->id(),
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'started_at' => now()->toDateTimeString(),
                'log_id' => $impersonationLog->id,
            ],
        ]);

        // Log in as the user
        Auth::login($user);

        // Redirect to tenant dashboard
        return redirect('/dashboard');
    }

    /**
     * Exit impersonation and return to super admin
     */
    public function exit(Request $request): RedirectResponse
    {
        // Check if currently impersonating
        if (! session()->has('impersonating')) {
            return redirect('/');
        }

        $impersonationData = session('impersonating');

        // Update audit log
        if (isset($impersonationData['log_id'])) {
            $log = ImpersonationLog::find($impersonationData['log_id']);
            if ($log) {
                $log->update([
                    'ended_at' => now(),
                    'exit_reason' => 'manual',
                ]);
            }
        }

        // Clear impersonation session
        session()->forget('impersonating');

        // Log out the impersonated user
        Auth::logout();

        // Redirect to super admin dashboard
        return redirect("http://admin.{$request->getHost()}/dashboard");
    }
}
