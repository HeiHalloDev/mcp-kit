<?php

declare(strict_types=1);

use HeiHallo\McpKit\Models\ServiceClient;
use HeiHallo\McpKit\Testing\Expectations;
use HeiHallo\McpKit\Testing\Guards;
use HeiHallo\McpKit\Testing\Mcp;
use HeiHallo\McpKit\Tests\Fixtures\Models\Team;
use HeiHallo\McpKit\Tests\Fixtures\Models\User;
use HeiHallo\McpKit\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

Expectations::register();

/**
 * @param  list<string>  $permissions
 */
function acmeUser(array $permissions = ['staff', 'things'], ?string $role = 'staff', array $attributes = []): User
{
    return User::query()->create([
        'name' => $attributes['name'] ?? 'Kari Nordmann',
        'email' => $attributes['email'] ?? 'kari-'.uniqid().'@example.test',
        'password' => bcrypt('irrelevant'),
        'role' => $role,
        'permissions' => $permissions,
        ...$attributes,
    ]);
}

function acmeAdmin(array $attributes = []): User
{
    return acmeUser(['staff', 'things', 'reports', 'admin'], 'admin', ['name' => 'Ada Admin', ...$attributes]);
}

function acmeTeam(string $name = 'Support'): Team
{
    return Team::query()->create(['name' => $name]);
}

/**
 * @param  list<string>  $abilities
 */
function acmeToken(User $user, array $abilities, string $label = 'laptop'): string
{
    return Mcp::token($user, $abilities, $label);
}

/**
 * The user with a real token attached, for AcmeServer::actingAs(...).
 *
 * @param  list<string>  $abilities
 */
function actingWith(User $user, array $abilities, string $label = 'laptop'): User
{
    return Mcp::actingWith($user, $abilities, $label);
}

Guards::actors(
    staff: fn () => acmeUser(),
    privileged: fn () => acmeAdmin(),
    blocked: fn () => acmeUser(['staff', 'things'], 'staff', ['blocked_at' => now()]),
    serviceClient: fn () => ServiceClient::query()->create(['name' => 'Harness', 'slug' => 'harness-'.uniqid()]),
);
