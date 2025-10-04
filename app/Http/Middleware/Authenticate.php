<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if (! $request->expectsJson()) {
            // If using super-admin guard, redirect to admin login
            if ($request->route() && $request->route()->getAction('middleware')) {
                $middleware = $request->route()->getAction('middleware');
                if (is_array($middleware) && in_array('auth:super-admin', $middleware)) {
                    return '/admin-login';
                }
            }

            return '/login';
        }

        return null;
    }
}
