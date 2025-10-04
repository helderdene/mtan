<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
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

        // Super admins bypass all tenant checks
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Verify user has a tenant_id set
        if (! $user->tenant_id) {
            abort(403, 'Unauthorized. No tenant context assigned to user.');
        }

        return $next($request);
    }
}
