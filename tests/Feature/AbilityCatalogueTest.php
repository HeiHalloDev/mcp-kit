<?php

declare(strict_types=1);

use HeiHallo\McpKit\Abilities\ConfigAbilityCatalogue;
use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers\AcmeReportsServer;

it('knows every catalogue ability, wildcard and alias', function () {
    $catalogue = app(AbilityCatalogue::class);

    expect($catalogue->exists('acme:things:read'))->toBeTrue()
        ->and($catalogue->exists('acme:*'))->toBeTrue()
        ->and($catalogue->exists('acme:things:*'))->toBeTrue()
        ->and($catalogue->exists('old:things:read'))->toBeTrue()
        ->and($catalogue->exists('acme:nothing:read'))->toBeFalse()
        ->and($catalogue->exists('other:*'))->toBeFalse()
        ->and($catalogue->canonical('old:things:read'))->toBe('acme:things:read')
        ->and($catalogue->isLegacy('old:things:read'))->toBeTrue()
        ->and($catalogue->prefixes())->toBe(['acme', 'old', 'reports']);
});

it('tells reads from writes, honouring read_abilities', function () {
    $catalogue = app(AbilityCatalogue::class);

    expect($catalogue->isWrite('acme:things:read'))->toBeFalse()
        ->and($catalogue->isWrite('acme:things:write'))->toBeTrue()
        ->and($catalogue->isWrite('acme:*'))->toBeTrue()
        ->and($catalogue->readOnly())->toBe(['acme:things:read', 'reports:read']);

    config()->set('mcp-kit.catalogue.abilities.acme:agents:chat', ['Chat', null, 'acme']);
    config()->set('mcp-kit.catalogue.read_abilities', ['acme:agents:chat']);

    expect(app()->make(ConfigAbilityCatalogue::class)->isWrite('acme:agents:chat'))->toBeFalse();
});

it('lists the wildcards that grant an ability, most specific first, none for explicit-only', function () {
    $catalogue = app(AbilityCatalogue::class);

    expect($catalogue->wildcardsFor('acme:things:write'))->toBe(['acme:things:*', 'acme:*', '*'])
        ->and($catalogue->wildcardsFor('reports:read'))->toBe(['reports:*', '*'])
        ->and($catalogue->wildcardsFor('acme:admin'))->toBe([])
        ->and($catalogue->isFamilyWildcard('acme:things:*'))->toBeTrue()
        ->and($catalogue->isWildcard('acme:*'))->toBeTrue()
        ->and($catalogue->isWildcard('*'))->toBeTrue();
});

it('includes the super wildcard when configured', function () {
    config()->set('mcp-kit.catalogue.super_wildcard', 'platform:*');
    $catalogue = app()->make(ConfigAbilityCatalogue::class);

    expect($catalogue->exists('platform:*'))->toBeTrue()
        ->and($catalogue->wildcardsFor('acme:things:read'))->toBe(['acme:things:*', 'acme:*', 'platform:*', '*'])
        ->and($catalogue->serversFor(['platform:*']))->toBe(['acme', 'reports'])
        ->and($catalogue->all())->toHaveKey('platform:*');
});

it('expands token abilities to the concrete abilities they grant', function () {
    $catalogue = app(AbilityCatalogue::class);

    expect($catalogue->expand(['acme:*']))->toBe(['acme:things:read', 'acme:things:write', 'acme:events:write'])
        ->and($catalogue->expand(['acme:things:*', 'reports:read']))->toBe(['acme:things:read', 'acme:things:write', 'reports:read'])
        ->and($catalogue->expand(['old:things:read']))->toBe(['acme:things:read'])
        ->and($catalogue->expand(['acme:admin']))->toBe(['acme:admin'])
        ->and($catalogue->expand(['*']))->not->toContain('acme:admin');
});

it('maps abilities to servers and walks shared wildcards', function () {
    $catalogue = app(AbilityCatalogue::class);

    expect($catalogue->serverFor('acme:things:read'))->toBe('acme')
        ->and($catalogue->serverFor('reports:*'))->toBe('reports')
        ->and($catalogue->serverFor('acme:things:*'))->toBe('acme')
        ->and($catalogue->serversFor(['reports:read']))->toBe(['reports'])
        ->and($catalogue->serversFor(['acme:*']))->toBe(['acme'])
        ->and($catalogue->serversFor(['*']))->toBe(['acme', 'reports'])
        ->and($catalogue->serversFor(['nonsense']))->toBe([]);

    // Two servers sharing one wildcard: the events server's abilities live under acme:*
    config()->set('mcp-kit.servers.events', ['class' => AcmeReportsServer::class, 'path' => '/mcp/events', 'wildcard' => 'acme:*']);
    config()->set('mcp-kit.catalogue.abilities.acme:events:read', ['Browse events', null, 'events']);
    $shared = app()->make(ConfigAbilityCatalogue::class);

    expect($shared->serversFor(['acme:*']))->toBe(['acme', 'events'])
        ->and($shared->serversFor(['acme:events:read']))->toBe(['events'])
        ->and($shared->all()['acme:*'])->toContain('Acme', 'Events');
});

it('keeps explicit-only abilities out of the ordinary catalogue', function () {
    $catalogue = app(AbilityCatalogue::class);

    expect($catalogue->all())->not->toHaveKey('acme:admin')
        ->and($catalogue->explicitOnly())->toBe(['acme:admin' => 'Change what other people may do'])
        ->and($catalogue->names())->toContain('acme:admin', 'acme:*', 'reports:*')
        ->and($catalogue->isExplicitOnly('acme:admin'))->toBeTrue()
        ->and($catalogue->all()['acme:*'])->toContain('Explicit-only abilities are never included');
});

it('resolves permissions and service-client writes', function () {
    $catalogue = app(AbilityCatalogue::class);

    expect($catalogue->requiredPermissions('acme:things:read'))->toBe(['things'])
        ->and($catalogue->requiredPermissions('acme:events:write'))->toBe([])
        ->and($catalogue->referencedPermissions())->toBe(['things', 'admin', 'reports'])
        ->and($catalogue->allowedForServiceClient('acme:things:read'))->toBeTrue()
        ->and($catalogue->allowedForServiceClient('acme:events:write'))->toBeTrue()
        ->and($catalogue->allowedForServiceClient('acme:things:write'))->toBeFalse()
        ->and($catalogue->description('acme:things:read'))->toBe('Search and view things')
        ->and($catalogue->description('old:things:read'))->toBe('Search and view things');
});

it('accepts the associative entry shape and a|b permissions', function () {
    config()->set('mcp-kit.catalogue.abilities.acme:exports:read', ['description' => 'Exports', 'permission' => 'things|reports', 'server' => 'acme']);
    $catalogue = app()->make(ConfigAbilityCatalogue::class);

    expect($catalogue->requiredPermissions('acme:exports:read'))->toBe(['things', 'reports'])
        ->and($catalogue->description('acme:exports:read'))->toBe('Exports')
        ->and($catalogue->serverFor('acme:exports:read'))->toBe('acme');
});
