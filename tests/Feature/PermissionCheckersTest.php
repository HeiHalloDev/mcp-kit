<?php

declare(strict_types=1);

use HeiHallo\McpKit\Permissions\GatePermissionChecker;
use HeiHallo\McpKit\Permissions\ModelPermissionChecker;
use HeiHallo\McpKit\Permissions\SpatiePermissionChecker;
use HeiHallo\McpKit\Tests\Fixtures\Models\SpatieLikeUser;
use HeiHallo\McpKit\Tests\Fixtures\Models\TraitUser;

it('checks permissions through the Gate by default', function () {
    $checker = app(GatePermissionChecker::class);
    $user = acmeUser(['staff', 'things']);
    $admin = acmeAdmin();

    expect($checker->hasAnyPermission($user, ['things']))->toBeTrue()
        ->and($checker->hasAnyPermission($user, ['reports', 'admin']))->toBeFalse()
        ->and($checker->isStaff($user))->toBeTrue()
        ->and($checker->isStaff(acmeUser(['things'])))->toBeFalse()
        ->and($checker->isPrivileged($user))->toBeFalse()
        ->and($checker->isPrivileged($admin))->toBeTrue()
        ->and($checker->knownPermissions())->toBe(['staff', 'things', 'reports', 'admin']);
});

it('falls back to the Gate abilities for known permissions', function () {
    config()->set('mcp-kit.permission_rules.known', null);

    expect(app(GatePermissionChecker::class)->knownPermissions())->toContain('staff', 'things');
});

it('checks permissions through the model\'s own methods', function () {
    $checker = app(ModelPermissionChecker::class);
    $user = TraitUser::query()->create(['name' => 'T', 'email' => 't@example.test', 'password' => 'x', 'role' => 'staff', 'permissions' => ['things']]);
    $admin = TraitUser::query()->create(['name' => 'A', 'email' => 'a@example.test', 'password' => 'x', 'role' => 'admin', 'permissions' => ['staff', 'admin']]);

    expect($checker->hasAnyPermission($user, ['things']))->toBeTrue()
        ->and($checker->hasAnyPermission($user, ['admin']))->toBeFalse()
        ->and($checker->isStaff($user))->toBeFalse()
        ->and($checker->isStaff($admin))->toBeTrue()
        ->and($checker->isPrivileged($admin))->toBeTrue()
        ->and($checker->isPrivileged($user))->toBeFalse();

    config()->set('mcp-kit.permission_rules.known', null);
    config()->set('mcp-kit.permission_rules.known_from', 'harness.permissions');
    config()->set('harness.permissions', ['things' => 'Things', 'reports' => 'Reports']);

    expect($checker->knownPermissions())->toBe(['things', 'reports']);
});

it('checks permissions through spatie\'s surface', function () {
    $checker = app(SpatiePermissionChecker::class);
    $user = SpatieLikeUser::query()->create(['name' => 'S', 'email' => 's@example.test', 'password' => 'x', 'permissions' => ['staff', 'things']]);
    $user->roles = ['Editor'];
    $owner = SpatieLikeUser::query()->create(['name' => 'O', 'email' => 'o@example.test', 'password' => 'x', 'permissions' => ['staff']]);
    $owner->roles = ['Owner'];

    config()->set('mcp-kit.permission_rules.privileged_roles', ['Owner', 'Dev']);

    expect($checker->hasAnyPermission($user, ['things']))->toBeTrue()
        ->and($checker->hasAnyPermission($user, ['admin']))->toBeFalse()
        ->and($checker->isStaff($user))->toBeTrue()
        ->and($checker->isPrivileged($user))->toBeFalse()
        ->and($checker->isPrivileged($owner))->toBeTrue()
        ->and($checker->knownPermissions())->toBe(['staff', 'things', 'reports', 'admin']);
});
