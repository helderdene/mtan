# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-10-04-role-based-authorization/spec.md

> Created: 2025-10-04
> Status: Ready for Implementation

## Tasks

### Phase 1: Database Setup

- [ ] **Task 1.1**: Create migration to add `role` enum column to `users` table
  - Column: `role` enum('super_admin', 'tenant_admin', 'tenant_user')
  - Default value: 'tenant_user'
  - Position: After `email` column
  - Index: Add index on `role` column

- [ ] **Task 1.2**: Create migration to add `tenant_id` foreign key to `users` table (if not exists)
  - Column: `tenant_id` (nullable, foreign key to `tenants.id`)
  - Constraint: Cascade on delete
  - Index: Add index on `tenant_id` column

- [ ] **Task 1.3**: Run migrations on development database
  - Command: `php artisan migrate`
  - Verify schema changes in database

- [ ] **Task 1.4**: Update User factory with role states
  - Add `superAdmin()`, `tenantAdmin()`, `tenantUser()` factory states
  - Set default role to 'tenant_user'
  - Ensure `tenant_id` is null for super_admin role

### Phase 2: User Model Enhancements

- [ ] **Task 2.1**: Add role checking methods to User model
  - `isSuperAdmin(): bool` - Check if role is 'super_admin'
  - `isTenantAdmin(): bool` - Check if role is 'tenant_admin'
  - `isTenantUser(): bool` - Check if role is 'tenant_user'
  - `hasRole(string $role): bool` - Generic role check
  - `belongsToTenant(int $tenantId): bool` - Tenant ownership check

- [ ] **Task 2.2**: Add tenant relationship to User model
  - `tenant()` - BelongsTo relationship to Tenant model
  - Ensure relationship works with multi-tenant architecture

- [ ] **Task 2.3**: Add `role` to fillable and casts arrays
  - Add 'role' to `$fillable` array
  - Add 'tenant_id' to `$fillable` array (if not exists)

### Phase 3: Middleware Implementation

- [ ] **Task 3.1**: Create `RequiresSuperAdmin` middleware
  - File: `app/Http/Middleware/RequiresSuperAdmin.php`
  - Check if user is authenticated and has super_admin role
  - Abort with 403 if unauthorized

- [ ] **Task 3.2**: Create `RequiresTenantAdmin` middleware
  - File: `app/Http/Middleware/RequiresTenantAdmin.php`
  - Check if user has tenant_admin or super_admin role
  - Verify tenant ownership (user belongs to current tenant context)
  - Allow super_admins to bypass tenant checks

- [ ] **Task 3.3**: Create `EnsureTenantAccess` middleware
  - File: `app/Http/Middleware/EnsureTenantAccess.php`
  - Verify user belongs to current tenant context
  - Super admins bypass this check
  - Abort with 403 if tenant context not set or user doesn't belong

- [ ] **Task 3.4**: Register middleware aliases in `app/Http/Kernel.php`
  - Alias: `super_admin` → `RequiresSuperAdmin::class`
  - Alias: `tenant_admin` → `RequiresTenantAdmin::class`
  - Alias: `tenant_access` → `EnsureTenantAccess::class`

### Phase 4: Authorization Gates

- [ ] **Task 4.1**: Create authorization gates in `AuthServiceProvider`
  - `isSuperAdmin` - Returns true if user is super_admin
  - `isTenantAdmin` - Returns true if user is tenant_admin or super_admin
  - `canAccessTenant` - Returns true if user belongs to specified tenant
  - `manageEmployees` - Returns true if user can manage employees
  - `manageDevices` - Returns true if user can manage devices
  - `viewReports` - Returns true if user can view reports

- [ ] **Task 4.2**: Test Gates in Tinker
  - Test each Gate with different user roles
  - Verify super_admin bypasses tenant checks
  - Verify tenant_admin restricted to own tenant

### Phase 5: Route Protection

- [ ] **Task 5.1**: Apply middleware to route groups in `routes/web.php`
  - Wrap authenticated routes with `['auth', 'tenant_access']` middleware
  - Wrap tenant admin routes with `['auth', 'tenant_access', 'tenant_admin']`
  - Wrap super admin routes with `['auth', 'super_admin']`

- [ ] **Task 5.2**: Update employee management routes
  - Apply `tenant_admin` middleware to all employee CRUD routes
  - Verify route protection works

- [ ] **Task 5.3**: Update device management routes
  - Apply `tenant_admin` middleware to all device routes
  - Verify route protection works

- [ ] **Task 5.4**: Update shift and department routes
  - Apply `tenant_admin` middleware to shift and department routes
  - Verify route protection works

- [ ] **Task 5.5**: Create super admin route group
  - Prefix: `/admin`
  - Middleware: `['auth', 'super_admin']`
  - Routes: Tenant management, system settings, MQTT config, metrics

- [ ] **Task 5.6**: Verify public and auth routes remain accessible
  - Test welcome page (no auth required)
  - Test login/register routes (guest middleware)
  - Test dashboard route (all authenticated users)
  - Test settings routes (all authenticated users)

### Phase 6: Error Handling

- [ ] **Task 6.1**: Create 403 error page component
  - File: `resources/js/pages/errors/403.vue`
  - Display error message from backend
  - Provide link to dashboard or login page

- [ ] **Task 6.2**: Update `HandleInertiaRequests` middleware
  - Share user role information with frontend
  - Share Gate results (can.manageEmployees, can.manageDevices, etc.)
  - Ensure error messages are displayed properly

- [ ] **Task 6.3**: Test error responses
  - Attempt unauthorized access with different roles
  - Verify 403 error page renders correctly
  - Verify error messages are descriptive

### Phase 7: Testing

- [ ] **Task 7.1**: Write User model unit tests
  - Test all role checking methods (isSuperAdmin, isTenantAdmin, etc.)
  - Test tenant relationship
  - File: `tests/Unit/UserModelTest.php`

- [ ] **Task 7.2**: Write middleware tests
  - Test `RequiresSuperAdmin` middleware (allow/deny scenarios)
  - Test `RequiresTenantAdmin` middleware (allow/deny scenarios)
  - Test `EnsureTenantAccess` middleware (tenant isolation)
  - Files: `tests/Feature/Middleware/*Test.php`

- [ ] **Task 7.3**: Write Gate tests
  - Test all authorization Gates (isSuperAdmin, isTenantAdmin, etc.)
  - Test with different user roles
  - File: `tests/Feature/Authorization/GateTest.php`

- [ ] **Task 7.4**: Write route protection integration tests
  - Test employee routes require tenant_admin
  - Test super admin routes require super_admin
  - Test tenant isolation (cross-tenant access denied)
  - File: `tests/Feature/Authorization/RouteProtectionTest.php`

- [ ] **Task 7.5**: Write tenant isolation tests
  - Test tenant admin cannot access other tenant data
  - Test super admin can access all tenant data
  - Test user without tenant_id is denied access
  - File: `tests/Feature/Authorization/TenantIsolationTest.php`

- [ ] **Task 7.6**: Run all tests and verify 100% pass rate
  - Command: `php artisan test`
  - Fix any failing tests
  - Verify code coverage targets met

### Phase 8: Documentation

- [ ] **Task 8.1**: Update `CLAUDE.md` with authorization patterns
  - Document middleware usage examples
  - Document Gate usage in controllers
  - Document testing patterns for authorization

- [ ] **Task 8.2**: Add inline documentation to middleware classes
  - PHPDoc comments for all methods
  - Explain authorization logic

- [ ] **Task 8.3**: Add inline documentation to User model methods
  - PHPDoc comments for role checking methods
  - Explain return values

### Phase 9: Seeding and Demo Data

- [ ] **Task 9.1**: Create seeder for users with different roles
  - Super admin user (email: admin@system.local, password: password)
  - Tenant admin user for each tenant
  - Tenant user for each tenant
  - File: `database/seeders/UserRoleSeeder.php`

- [ ] **Task 9.2**: Update database seeder to call UserRoleSeeder
  - Add to `DatabaseSeeder.php`

- [ ] **Task 9.3**: Run seeders on development database
  - Command: `php artisan db:seed`
  - Verify users created with correct roles

- [ ] **Task 9.4**: Test login with different roles
  - Login as super_admin and verify access to all routes
  - Login as tenant_admin and verify restricted access
  - Login as tenant_user and verify limited access

### Phase 10: Deployment Preparation

- [ ] **Task 10.1**: Add migration to production deployment checklist
  - Ensure `role` and `tenant_id` columns are added to production database
  - Verify existing users get default role assigned

- [ ] **Task 10.2**: Create rollback migration
  - Remove `role` and `tenant_id` columns if deployment fails
  - Test rollback on staging environment

- [ ] **Task 10.3**: Update deployment documentation
  - Document new middleware and authorization system
  - Provide examples of role assignment

- [ ] **Task 10.4**: Test on staging environment
  - Deploy to staging
  - Run all authorization tests
  - Manually test with different user roles
  - Verify tenant isolation works correctly

## Task Dependencies

```
Phase 1 (Database) → Phase 2 (User Model) → Phase 3 (Middleware) → Phase 4 (Gates) → Phase 5 (Routes)
                                                                                        ↓
Phase 9 (Seeding) ← Phase 8 (Documentation) ← Phase 7 (Testing) ← Phase 6 (Error Handling)
       ↓
Phase 10 (Deployment)
```

## Estimated Time

- Phase 1: 1 hour
- Phase 2: 1 hour
- Phase 3: 2 hours
- Phase 4: 1 hour
- Phase 5: 2 hours
- Phase 6: 1 hour
- Phase 7: 4 hours
- Phase 8: 1 hour
- Phase 9: 1 hour
- Phase 10: 2 hours

**Total**: ~16 hours (2 days)

## Success Criteria

- All migrations run successfully
- User model has role checking methods
- All middleware classes created and registered
- All authorization Gates defined
- All routes protected with appropriate middleware
- 403 error page displays correctly
- All tests pass (100% coverage for critical paths)
- Documentation updated
- Demo users seeded with different roles
- Staging deployment successful
