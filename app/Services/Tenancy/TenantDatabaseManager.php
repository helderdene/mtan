<?php

namespace App\Services\Tenancy;

use App\DTOs\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * TenantDatabaseManager
 *
 * Manages tenant database lifecycle:
 * - Creating tenant databases
 * - Dropping tenant databases
 * - Setting up dynamic database connections
 * - Running tenant migrations
 * - Full tenant provisioning workflow
 */
class TenantDatabaseManager
{
    /**
     * Create a new tenant database
     */
    public function createDatabase(Tenant $tenant): bool
    {
        try {
            $databaseName = $tenant->database_name;

            // Connect to MySQL without specifying a database
            $pdo = $this->getPdo($tenant->database_host);

            // Create database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$databaseName}`
                       CHARACTER SET utf8mb4
                       COLLATE utf8mb4_unicode_ci");

            return true;
        } catch (\PDOException $e) {
            // Log error but don't throw - allows for idempotent operations
            logger()->error("Failed to create tenant database: {$tenant->database_name}", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenant->id,
            ]);

            return false;
        }
    }

    /**
     * Drop a tenant database
     */
    public function dropDatabase(string $databaseName): bool
    {
        try {
            // Safety check: only allow dropping databases with 'tenant_' prefix
            if (! str_starts_with($databaseName, 'tenant_')) {
                throw new \InvalidArgumentException(
                    "Database name must start with 'tenant_' for safety. Got: {$databaseName}"
                );
            }

            $pdo = $this->getPdo();

            $pdo->exec("DROP DATABASE IF EXISTS `{$databaseName}`");

            return true;
        } catch (\Exception $e) {
            logger()->error("Failed to drop tenant database: {$databaseName}", [
                'error' => $e->getMessage(),
            ]);

            return true; // Return true even on error to avoid blocking cleanup
        }
    }

    /**
     * Set up dynamic tenant database connection
     *
     * Configures the 'tenant' connection to point to the tenant's database
     */
    public function setupTenantConnection(Tenant $tenant): string
    {
        $connectionName = 'tenant';

        // Configure dynamic connection
        Config::set("database.connections.{$connectionName}", [
            'driver' => 'mysql',
            'host' => $tenant->database_host,
            'port' => env('DB_PORT', '3306'),
            'database' => $tenant->database_name,
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ]);

        // Purge existing connection if any
        DB::purge($connectionName);

        // Reconnect with new configuration
        DB::reconnect($connectionName);

        return $connectionName;
    }

    /**
     * Run migrations on tenant database
     */
    public function runMigrations(Tenant $tenant): bool
    {
        try {
            // Ensure connection is set up
            $this->setupTenantConnection($tenant);

            // Run migrations on tenant database
            // Use --path option to run only tenant-specific migrations
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,  // Force in production
            ]);

            $output = Artisan::output();
            logger()->info("Ran migrations for tenant: {$tenant->database_name}", [
                'output' => $output,
            ]);

            return true;
        } catch (\Exception $e) {
            logger()->error("Failed to run migrations for tenant: {$tenant->database_name}", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenant->id,
            ]);

            return false;
        }
    }

    /**
     * Full tenant provisioning workflow
     *
     * Creates database, sets up connection, and runs migrations
     */
    public function provisionTenant(Tenant $tenant): bool
    {
        return $this->provisionTenantDatabase($tenant);
    }

    /**
     * Alias for provisionTenant
     *
     * Creates database, sets up connection, and runs migrations
     */
    public function provisionTenantDatabase(Tenant $tenant): bool
    {
        try {
            // Step 1: Create database
            if (! $this->createDatabase($tenant)) {
                return false;
            }

            // Step 2: Set up connection
            $this->setupTenantConnection($tenant);

            // Step 3: Run migrations
            if (! $this->runMigrations($tenant)) {
                // Rollback: drop database if migrations fail
                $this->dropDatabase($tenant->database_name);

                return false;
            }

            // Step 4: Create default admin user
            $adminSeeder = new TenantAdminSeeder();
            $admin = $adminSeeder->createDefaultAdmin($tenant);

            if (!$admin) {
                logger()->warning("Admin user creation failed for tenant: {$tenant->company_name}", [
                    'tenant_id' => $tenant->id,
                ]);
            }

            logger()->info("Successfully provisioned tenant: {$tenant->company_name}", [
                'tenant_id' => $tenant->id,
                'database_name' => $tenant->database_name,
                'admin_created' => $admin !== null,
            ]);

            return true;
        } catch (\Exception $e) {
            logger()->error("Failed to provision tenant: {$tenant->company_name}", [
                'error' => $e->getMessage(),
                'tenant_id' => $tenant->id,
            ]);

            // Attempt cleanup
            $this->dropDatabase($tenant->database_name);

            return false;
        }
    }

    /**
     * Get PDO connection for database operations
     */
    private function getPdo(?string $host = null): \PDO
    {
        $host = $host ?? env('DB_HOST', '127.0.0.1');
        $username = env('DB_USERNAME', 'root');
        $password = env('DB_PASSWORD', '');

        return new \PDO(
            "mysql:host={$host}",
            $username,
            $password,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }
}
