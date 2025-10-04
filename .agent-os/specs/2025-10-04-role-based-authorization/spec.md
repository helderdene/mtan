# Spec Requirements Document

> Spec: Role-Based Authorization Middleware
> Created: 2025-10-04
> Status: Planning

## Overview

Implement a role-based authorization middleware system to control access to administrative and tenant-specific routes in the multi-tenant attendance monitoring system. The system must support three core roles (super_admin, tenant_admin, tenant_user) with proper tenant isolation to ensure users can only access resources within their assigned tenant context.

The middleware will integrate with Laravel's authorization system (Gates and Policies) and protect route groups based on role requirements. This provides a foundation for Phase 2 of the roadmap while remaining simple enough to avoid complex permission systems planned for Phase 4.

## User Stories

**As a Super Admin**, I want to access all tenant databases and system-wide settings so that I can manage the entire platform, provision new tenants, and monitor system health across all organizations.

**As a Tenant Admin**, I want to manage employees, devices, shifts, and view attendance reports within my organization so that I can configure the attendance system without affecting other tenants or accessing super admin functions.

**As a Tenant User (Regular Employee/Manager)**, I want to view my own attendance records and submit leave requests so that I can track my work hours, but I should not be able to access admin configuration pages or other employees' sensitive data.

**As a Developer**, I want clear middleware that throws unauthorized exceptions when users attempt to access routes outside their role permissions so that security is enforced consistently across all controllers.

**As a Security Auditor**, I want tenant isolation enforced at the authorization layer so that no user can access data from a different tenant, even if they manipulate URLs or API requests.

## Spec Scope

**In Scope:**
- Three core roles: `super_admin`, `tenant_admin`, `tenant_user`
- Authorization middleware classes: `RequiresSuperAdmin`, `RequiresTenantAdmin`, `RequiresAuthentication`
- Laravel Gates for role checking: `isSuperAdmin`, `isTenantAdmin`, `canAccessTenant`
- Route group protection strategy in `routes/web.php` and `routes/api.php`
- Multi-tenant isolation checks (ensure user belongs to current tenant context)
- Role assignment via `users` table column (`role` enum field)
- Authorization exception handling with proper HTTP 403 responses
- Documentation of protected routes in technical spec

**Out of Scope:**
- Granular permission systems (e.g., "can_edit_employees", "can_view_reports") - deferred to Phase 4
- LDAP/Active Directory integration for role syncing - Phase 4 feature
- Custom role creation by tenant admins - Phase 4 feature
- Time-based access restrictions (e.g., "admin only during business hours")
- Audit logging of authorization failures - will be added in Phase 3 (Audit & Compliance)
- API token-based role management - Phase 3 feature
- Frontend role-based UI rendering (button hiding, menu filtering) - separate frontend spec

## Out of Scope

- **Complex Permission Systems**: No granular permissions like "can_edit_employees", "can_delete_devices", etc. This is planned for Phase 4 (Advanced Permissions & Compliance) and requires a separate permissions table, role-permission mappings, and a more sophisticated authorization layer.

- **LDAP/Active Directory Integration**: No synchronization of roles from external directory services. This is a Phase 4 enterprise feature requiring LDAP client integration and role mapping configuration.

- **Dynamic Role Creation**: Tenant admins cannot create custom roles. Only the three predefined roles (super_admin, tenant_admin, tenant_user) are supported in this phase.

- **Audit Logging**: Authorization failures and role changes are not logged in this spec. Comprehensive audit logging is planned for Phase 3 (Audit & Compliance Module).

- **Frontend Authorization**: This spec focuses on backend middleware only. Frontend role-based UI rendering (hiding buttons, filtering menus) will be addressed in a separate frontend authorization spec.

## Expected Deliverable

**Middleware Implementation:**
- `app/Http/Middleware/RequiresSuperAdmin.php` - Middleware that checks if authenticated user has `super_admin` role
- `app/Http/Middleware/RequiresTenantAdmin.php` - Middleware that checks if user has `tenant_admin` or `super_admin` role within current tenant context
- `app/Http/Middleware/RequiresAuthentication.php` - Middleware for tenant_user level access (already exists via `auth` middleware, may need tenant isolation enhancement)

**Authorization Gates:**
- `isSuperAdmin` - Returns true if user role is `super_admin`
- `isTenantAdmin` - Returns true if user role is `tenant_admin` or `super_admin` and belongs to current tenant
- `canAccessTenant` - Returns true if user belongs to specified tenant (prevents cross-tenant access)

**Database Changes:**
- Migration to add `role` enum column to `users` table (values: `super_admin`, `tenant_admin`, `tenant_user`)
- Default value: `tenant_user`
- Index on `role` column for performance

**Route Protection:**
- Route groups in `routes/web.php` protected with appropriate middleware
- Super admin routes (tenant provisioning, system settings) protected with `RequiresSuperAdmin`
- Tenant admin routes (employee management, device config) protected with `RequiresTenantAdmin`
- Regular user routes (view own attendance, submit leave) protected with `RequiresAuthentication`

**Testing:**
- Pest tests for each middleware class
- Tests for Gates (isSuperAdmin, isTenantAdmin, canAccessTenant)
- Integration tests for route protection (attempt access with wrong role, expect 403)
- Tenant isolation tests (user from tenant A cannot access tenant B routes)

**Documentation:**
- Technical specification detailing middleware logic, Gates, and route protection strategy
- API specification documenting which routes require which roles
- Updated `CLAUDE.md` with authorization patterns and testing examples

## Spec Documentation

- Tasks: @.agent-os/specs/2025-10-04-role-based-authorization/tasks.md
- Technical Specification: @.agent-os/specs/2025-10-04-role-based-authorization/sub-specs/technical-spec.md
- API Specification: @.agent-os/specs/2025-10-04-role-based-authorization/sub-specs/api-spec.md
- Tests Specification: @.agent-os/specs/2025-10-04-role-based-authorization/sub-specs/tests.md
