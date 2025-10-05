<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequiresTenantAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized. Authentication required.');
        }

        // Super admins can access all tenant admin routes
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Check if user is tenant admin
        if (! $user->isTenantAdmin()) {
            abort(403, 'Unauthorized. Tenant admin access required.');
        }

        return $next($request);
    }
}
