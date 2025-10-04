# Database Schema

This is the database schema implementation for the spec detailed in @.agent-os/specs/2025-10-03-super-admin-login/spec.md

## Schema Changes

### New Table: impersonation_logs (Central Database)

Create a new table in the central database to track all super admin impersonation activities for audit and compliance purposes.

**Migration:** `database/migrations/central/2025_10_03_000001_create_impersonation_logs_table.php`

```php
Schema::connection('central')->create('impersonation_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('super_admin_id')->constrained('super_admins')->onDelete('cascade');
    $table->string('tenant_id')->index();
    $table->unsignedBigInteger('user_id'); // Tenant user ID (not FK due to different DB)
    $table->string('user_email'); // Denormalized for audit trail
    $table->timestamp('started_at');
    $table->timestamp('ended_at')->nullable();
    $table->string('ip_address', 45);
    $table->text('user_agent')->nullable();
    $table->string('exit_reason')->nullable(); // 'manual', 'timeout', 'forced'
    $table->timestamps();

    $table->index(['super_admin_id', 'started_at']);
    $table->index(['tenant_id', 'started_at']);
});
```

**Rationale:**
- `super_admin_id` - Links to which super admin performed the impersonation
- `tenant_id` - Which tenant's user was impersonated (string to match tenants table)
- `user_id` & `user_email` - Impersonated user details (email denormalized for permanent audit trail)
- `started_at` / `ended_at` - Impersonation session duration for compliance tracking
- `ip_address` & `user_agent` - Security audit information
- `exit_reason` - How impersonation ended (manual exit, session timeout, forced logout)

**Indexes:**
- Composite index on (super_admin_id, started_at) for "who did what when" queries
- Composite index on (tenant_id, started_at) for "who accessed this tenant" queries

### Existing Table Verification: super_admins (Central Database)

The `super_admins` table already exists (per CLAUDE.md) with the required structure:

```php
// Existing migration: database/migrations/central/2024_01_01_000003_create_super_admins_table.php
Schema::create('super_admins', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->timestamp('email_verified_at')->nullable();
    $table->text('two_factor_secret')->nullable(); // For future 2FA
    $table->boolean('two_factor_enabled')->default(false);
    $table->timestamp('last_login_at')->nullable();
    $table->string('last_login_ip', 45)->nullable();
    $table->boolean('is_active')->default(true);
    $table->rememberToken();
    $table->timestamps();
});
```

**No modifications needed** - This table is already properly structured for authentication.

## Data Migration

### Update Existing Super Admin Record

If a super admin already exists in the database (created via tinker), ensure it has proper structure:

```php
// Run this via tinker or seeder
DB::connection('central')->table('super_admins')->where('email', 'admin@example.com')->update([
    'is_active' => true,
    'email_verified_at' => now(),
]);
```

## Performance Considerations

- **Impersonation Logs Growth**: Add scheduled task to archive logs older than 1 year
- **Session Isolation**: Separate session keys prevent cross-contamination and improve cache hit rates
- **Index Strategy**: Composite indexes support common audit queries without full table scans

## Data Integrity Rules

- `impersonation_logs.started_at` must always be set when record is created
- `impersonation_logs.ended_at` should be set when impersonation ends (nullable handles abrupt disconnections)
- `super_admins.is_active = false` prevents login but preserves audit trail
- Foreign key on `super_admin_id` ensures referential integrity with CASCADE delete
