<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Contracts\UserDescriber;
use HeiHallo\McpKit\Describe\AutoDescriber;
use HeiHallo\McpKit\Describe\GenericDescriber;
use HeiHallo\McpKit\Describe\PermissionsTraitDescriber;
use HeiHallo\McpKit\Describe\SpatieRolesDescriber;
use HeiHallo\McpKit\Describe\UserDescription;
use HeiHallo\McpKit\Principal;
use HeiHallo\McpKit\Tests\Fixtures\Models\SpatieLikeUser;
use HeiHallo\McpKit\Tests\Fixtures\Models\TraitUser;

test('the generic describer reads role, team and held permissions', function () {
    $team = acmeTeam('Sales');
    $user = acmeUser(['staff', 'things'], 'staff', ['team_id' => $team->id]);
    $description = app(UserDescriber::class)->describe(app(PrincipalResolver::class)->resolve($user));

    expect($description)->toBeInstanceOf(UserDescription::class)
        ->and($description->role)->toBe('staff')
        ->and($description->team)->toBe('Sales')
        ->and($description->permissions)->toBe(['staff', 'things'])
        ->and($description->knowsRole())->toBeTrue()
        ->and($description->knowsTeam())->toBeTrue()
        ->and($description->privileged)->toBeFalse();

    config()->set('mcp-kit.describer_options.permission_labels', ['things' => 'Things']);

    expect(app(GenericDescriber::class)->describe(app(PrincipalResolver::class)->resolve($user))->permissions)->toBe(['staff', 'Things']);
});

test('the auto describer picks by the user class', function () {
    $auto = app(AutoDescriber::class);
    $pick = fn (Principal $p) => (fn () => $this->describerFor($p))->call($auto);

    $plain = app(PrincipalResolver::class)->resolve(acmeUser());
    $trait = app(PrincipalResolver::class)->resolve(TraitUser::query()->create(['name' => 'T', 'email' => 't@example.test', 'password' => 'x', 'permissions' => ['things']]));
    $spatie = app(PrincipalResolver::class)->resolve(SpatieLikeUser::query()->create(['name' => 'S', 'email' => 's@example.test', 'password' => 'x', 'permissions' => ['staff']]));

    expect($pick($plain))->toBeInstanceOf(GenericDescriber::class)
        ->and($pick($trait))->toBeInstanceOf(PermissionsTraitDescriber::class)
        ->and($pick($spatie))->toBeInstanceOf(SpatieRolesDescriber::class);
});

test('the spatie describer uses role names and all permissions', function () {
    $user = SpatieLikeUser::query()->create(['name' => 'S', 'email' => 's@example.test', 'password' => 'x', 'permissions' => ['staff', 'things'], 'role' => null]);
    $user->roles = ['Editor', 'Support'];

    $description = app(UserDescriber::class)->describe(app(PrincipalResolver::class)->resolve($user));

    expect($description->role)->toBe('Editor, Support')->and($description->permissions)->toBe(['staff', 'things']);
});

test('a description can be extended with inboxes and facts', function () {
    $base = app(UserDescriber::class)->describe(app(PrincipalResolver::class)->resolve(acmeUser()));
    $extended = $base->with(['inboxes' => ['Support', 'Sales'], 'facts' => ['Works Mondays to Thursdays']]);

    expect($extended->inboxes)->toBe(['Support', 'Sales'])
        ->and($extended->facts)->toBe(['Works Mondays to Thursdays'])
        ->and($extended->name)->toBe($base->name);
});
