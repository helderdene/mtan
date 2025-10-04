<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth('super-admin')->check()) {
            return redirect()->route('super-admin.login');
        }

        if (! auth('super-admin')->user()->is_active) {
            auth('super-admin')->logout();
            return redirect()->route('super-admin.login')
                ->with('error', 'Your account has been deactivated.');
        }

        return $next($request);
    }
}
