<?php

namespace App\Providers;

use App\Models\DeviceRegistry;
use App\Observers\DeviceRegistryObserver;
use Illuminate\Support\ServiceProvider;

class ObserverServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        DeviceRegistry::observe(DeviceRegistryObserver::class);
    }
}
