<?php

use App\Models\User;

uses(Tests\TestCase::class);

test('user can be created with super_admin role', function () {
    $user = User::factory()->superAdmin()->make();

    expect($user->role)->toBe('super_admin')
        ->and($user->tenant_id)->toBeNull();
});

test('user can be created with tenant_admin role', function () {
    $tenantId = 'tenant_test123';
    $user = User::factory()->tenantAdmin($tenantId)->make();

    expect($user->role)->toBe('tenant_admin')
        ->and($user->tenant_id)->toBe($tenantId);
});

test('user can be created with tenant_user role', function () {
    $tenantId = 'tenant_test123';
    $user = User::factory()->tenantUser($tenantId)->make();

    expect($user->role)->toBe('tenant_user')
        ->and($user->tenant_id)->toBe($tenantId);
});

test('isSuperAdmin returns true for super_admin role', function () {
    $user = User::factory()->superAdmin()->make();

    expect($user->isSuperAdmin())->toBeTrue();
});

test('isSuperAdmin returns false for other roles', function () {
    $tenantAdmin = User::factory()->tenantAdmin()->make();
    $tenantUser = User::factory()->tenantUser()->make();

    expect($tenantAdmin->isSuperAdmin())->toBeFalse()
        ->and($tenantUser->isSuperAdmin())->toBeFalse();
});

test('isTenantAdmin returns true for tenant_admin role', function () {
    $user = User::factory()->tenantAdmin()->make();

    expect($user->isTenantAdmin())->toBeTrue();
});

test('isTenantAdmin returns false for other roles', function () {
    $superAdmin = User::factory()->superAdmin()->make();
    $tenantUser = User::factory()->tenantUser()->make();

    expect($superAdmin->isTenantAdmin())->toBeFalse()
        ->and($tenantUser->isTenantAdmin())->toBeFalse();
});

test('isTenantUser returns true for tenant_user role', function () {
    $user = User::factory()->tenantUser()->make();

    expect($user->isTenantUser())->toBeTrue();
});

test('isTenantUser returns false for other roles', function () {
    $superAdmin = User::factory()->superAdmin()->make();
    $tenantAdmin = User::factory()->tenantAdmin()->make();

    expect($superAdmin->isTenantUser())->toBeFalse()
        ->and($tenantAdmin->isTenantUser())->toBeFalse();
});

test('hasRole returns true for matching role', function () {
    $user = User::factory()->tenantAdmin()->make();

    expect($user->hasRole('tenant_admin'))->toBeTrue();
});

test('hasRole returns false for non-matching role', function () {
    $user = User::factory()->tenantAdmin()->make();

    expect($user->hasRole('super_admin'))->toBeFalse();
});

test('belongsToTenant returns true for matching tenant_id', function () {
    $tenantId = 'tenant_test123';
    $user = User::factory()->tenantUser($tenantId)->make();

    expect($user->belongsToTenant($tenantId))->toBeTrue();
});

test('belongsToTenant returns false for non-matching tenant_id', function () {
    $user = User::factory()->tenantUser('tenant_test123')->make();

    expect($user->belongsToTenant('tenant_different'))->toBeFalse();
});

test('default user factory creates tenant_user', function () {
    $user = User::factory()->make();

    expect($user->role)->toBe('tenant_user');
});
