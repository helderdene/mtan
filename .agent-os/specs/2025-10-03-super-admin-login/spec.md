# Spec Requirements Document

> Spec: Super Admin Login Separation
> Created: 2025-10-03

## Overview

Implement a separate authentication system for super administrators that is completely isolated from tenant user authentication, accessible via a dedicated subdomain. This enables system-level administrative access while maintaining strict security boundaries between super admin operations and tenant data access.

## User Stories

### Super Admin System Access

As a super administrator, I want to log in through a dedicated admin portal at admin.yourdomain.com, so that I can access system-wide administrative features without conflicting with tenant authentication.

The super admin navigates to the admin subdomain, enters credentials stored in the central database's super_admins table, and gains access to a system-level dashboard with tenant management, system configuration, and monitoring capabilities.

### Tenant Impersonation for Support

As a super administrator, I want to impersonate a tenant user for troubleshooting purposes, so that I can diagnose issues from the tenant's perspective without needing their credentials.

From the super admin dashboard, the admin selects a tenant from the tenant list and clicks "Impersonate." The system creates a tenant-scoped session while maintaining the super admin's original session, allowing the admin to experience the tenant interface and then return to super admin context.

### Tenant User Normal Login

As a tenant user, I want to log in through my organization's subdomain (e.g., acme.yourdomain.com), so that I can access my attendance data without being aware of super admin functionality.

The tenant user navigates to their subdomain, authenticates against their tenant database's users table, and accesses their tenant-scoped dashboard with no visibility into super admin features.

## Spec Scope

1. **Subdomain-Based Authentication Routing** - Implement middleware that detects admin subdomain and routes to super admin authentication guard
2. **Super Admin Authentication Guard** - Create dedicated Laravel guard that authenticates against central database's super_admins table
3. **Super Admin Dashboard** - Build Vue.js admin interface with tenant management, system monitoring, and impersonation controls
4. **Tenant Impersonation System** - Implement session-based tenant impersonation with audit logging and easy context switching
5. **Tenant Authentication Isolation** - Ensure tenant users authenticate only against their tenant database with no super admin access

## Out of Scope

- Two-factor authentication for super admins (future enhancement)
- Super admin API authentication (current focus is web interface only)
- Granular super admin roles/permissions (single super admin role for now)
- Super admin mobile application

## Expected Deliverable

1. Super admin can successfully log in at admin.yourdomain.com and access a dedicated dashboard
2. Tenant users continue to log in at their subdomain (e.g., acme.yourdomain.com) with existing functionality unchanged
3. Super admin can impersonate any tenant user, navigate their interface, and return to super admin context
4. All authentication sessions are properly isolated with audit logging for impersonation actions
