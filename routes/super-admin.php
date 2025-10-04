<?php

use App\Http\Controllers\SuperAdmin\AuthController;
use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\DeviceRegistryController;
use App\Http\Controllers\SuperAdmin\ImpersonationController;
use App\Http\Controllers\SuperAdmin\TenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super Admin Routes
|--------------------------------------------------------------------------
|
| These routes are for super admin access via the admin subdomain
| (e.g., admin.yourdomain.com). All routes use the 'super-admin' guard
| for authentication and are completely isolated from tenant routes.
|
*/

// Admin subdomain routes
// In production, this would use admin.{yourdomain.com}
// For testing, we use the exact domain from APP_URL
$adminDomain = 'admin.' . parse_url(config('app.url'), PHP_URL_HOST);

Route::domain($adminDomain)->group(function () {

    // Guest routes (unauthenticated super admins)
    Route::middleware('guest:super-admin')->group(function () {
        Route::get('/admin-login', [AuthController::class, 'showLogin'])->name('super-admin.login');
        Route::post('/admin-login', [AuthController::class, 'login'])->name('super-admin.login.post');
    });

    // Authenticated super admin routes
    Route::middleware('auth:super-admin')->group(function () {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('super-admin.dashboard');

        // Tenant management
        Route::get('/tenants', [TenantController::class, 'index'])->name('super-admin.tenants.index');
        Route::get('/tenants/create', [TenantController::class, 'create'])->name('super-admin.tenants.create');
        Route::post('/tenants', [TenantController::class, 'store'])->name('super-admin.tenants.store');
        Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('super-admin.tenants.show');
        Route::get('/tenants/{tenant}/edit', [TenantController::class, 'edit'])->name('super-admin.tenants.edit');
        Route::put('/tenants/{tenant}', [TenantController::class, 'update'])->name('super-admin.tenants.update');
        Route::delete('/tenants/{tenant}', [TenantController::class, 'destroy'])->name('super-admin.tenants.destroy');
        Route::post('/tenants/{tenant}/provision', [TenantController::class, 'provision'])->name('super-admin.tenants.provision');
        Route::post('/tenants/{tenant}/send-credentials', [TenantController::class, 'sendCredentials'])->name('super-admin.tenants.send-credentials');

        // Device Registry Management
        Route::resource('device-registry', DeviceRegistryController::class, [
            'as' => 'super-admin',
        ]);

        // Impersonation
        Route::post('/tenants/{tenant}/impersonate/{user}', [ImpersonationController::class, 'start'])->name('super-admin.impersonate.start');

        // Logout
        Route::post('/logout', [AuthController::class, 'logout'])->name('super-admin.logout');
    });
});

// Exit impersonation route (available on any domain when impersonating)
Route::post('/exit-impersonation', [ImpersonationController::class, 'exit'])
    ->middleware(['web', 'auth'])
    ->name('super-admin.impersonate.exit');
