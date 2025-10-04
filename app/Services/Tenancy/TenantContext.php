<?php

namespace App\Services\Tenancy;

use App\DTOs\Tenant;

/**
 * TenantContext
 *
 * Stores the current tenant for the request lifecycle.
 * This is a singleton service bound to the service container.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    /**
     * Set the current tenant
     */
    public function setTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    /**
     * Get the current tenant
     */
    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Check if a tenant is set
     */
    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Get the current tenant ID
     */
    public function getTenantId(): ?string
    {
        return $this->tenant?->id;
    }

    /**
     * Get the current tenant's database name
     */
    public function getDatabaseName(): ?string
    {
        return $this->tenant?->database_name;
    }

    /**
     * Clear the current tenant (useful for testing)
     */
    public function clear(): void
    {
        $this->tenant = null;
    }
}
