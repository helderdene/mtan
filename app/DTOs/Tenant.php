<?php

namespace App\DTOs;

/**
 * Tenant Data Transfer Object
 *
 * Represents a tenant resolved from the central database.
 * This is a read-only value object used for tenant identification and routing.
 */
class Tenant
{
    public function __construct(
        public readonly string $id,
        public readonly string $company_name,
        public readonly string $subdomain,
        public readonly ?string $domain,
        public readonly string $database_name,
        public readonly string $database_host,
        public readonly string $subscription_plan,
        public readonly int $max_employees,
        public readonly int $max_devices,
        public readonly bool $is_active,
        public readonly ?array $features = null,
    ) {}

    /**
     * Create Tenant from database row
     */
    public static function fromDatabase(object $row): self
    {
        return new self(
            id: $row->id,
            company_name: $row->company_name,
            subdomain: $row->subdomain,
            domain: $row->domain,
            database_name: $row->database_name,
            database_host: $row->database_host,
            subscription_plan: $row->subscription_plan,
            max_employees: $row->max_employees,
            max_devices: $row->max_devices,
            is_active: (bool) $row->is_active,
            features: $row->features ? json_decode($row->features, true) : null,
        );
    }

    /**
     * Check if tenant is active and subscription is valid
     */
    public function isAccessible(): bool
    {
        return $this->is_active;
    }
}
