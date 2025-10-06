<?php

namespace App\Traits;

trait UsesTenantConnection
{
    /**
     * Get the database connection for the model.
     *
     * Uses the default connection in testing environment,
     * otherwise uses the tenant connection.
     */
    public function getConnectionName()
    {
        // Use default connection in testing environment
        if (app()->bound('env') && app()->environment('testing')) {
            return config('database.default');
        }

        return $this->connection ?? 'tenant';
    }
}
