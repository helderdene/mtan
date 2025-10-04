<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /**
     * Show the super admin login form
     */
    public function showLogin(): Response
    {
        return Inertia::render('super-admin/auth/Login');
    }

    /**
     * Handle super admin login
     */
    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials = $request->only('email', 'password');

        // Attempt to authenticate using the super-admin guard
        if (Auth::guard('super-admin')->attempt($credentials, $request->boolean('remember'))) {
            $superAdmin = Auth::guard('super-admin')->user();

            // Check if super admin is active
            if (!$superAdmin->is_active) {
                Auth::guard('super-admin')->logout();

                throw ValidationException::withMessages([
                    'email' => 'This account is inactive.',
                ]);
            }

            // Update last login info
            $superAdmin->update([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ]);

            $request->session()->regenerate();

            return redirect()->intended(route('super-admin.dashboard'));
        }

        throw ValidationException::withMessages([
            'email' => 'The provided credentials do not match our records.',
        ]);
    }

    /**
     * Handle super admin logout
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('super-admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Redirect to admin login page
        return response()->redirectTo('/admin-login');
    }
}
