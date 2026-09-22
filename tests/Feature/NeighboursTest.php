<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\GapStore;
use HeiHallo\McpKit\Contracts\GroundRules;
use HeiHallo\McpKit\Mcp\Tools\ReportGapTool;
use HeiHallo\McpKit\Neighbours\Neighbours;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers\AcmeServer;

/**
 * An assistant that runs out of road here should be told where the road
 * continues — not left to work around it for two hundred calls.
 */
beforeEach(function () {
    config()->set('mcp-kit.neighbours', [
        'crm' => [
            'label' => 'Acme CRM',
            'owns' => 'People and everything around them: customers, signups, invoices',
            'tools' => ['search_contacts', 'list_signups'],
            'match' => ['customer', 'kunde', 'signup', 'invoice'],
            'ask' => 'You may already have it; if not, ask Ada for a token.',
        ],
    ]);
});

it('ships no neighbours of its own, so one client never hears about another', function () {
    $defaults = require dirname(__DIR__, 2).'/config/mcp-kit.php';

    expect($defaults['neighbours'])->toBe([])
        ->and($defaults['hints']['instead_of'])->toBe([]);

    config()->set('mcp-kit.neighbours', []);

    expect(app(Neighbours::class)->any())->toBeFalse()
        ->and(app(Neighbours::class)->instructions())->toBeNull()
        ->and(app(Neighbours::class)->match('a customer signup'))->toBeNull();
});

it('puts what the app does not hold into every server\'s instructions', function () {
    $instructions = app(GroundRules::class)->instructions('acme', 'The Acme server.');

    expect($instructions)->toContain('What is not here')
        ->toContain('Acme CRM')
        ->toContain('search_contacts')
        ->toContain('ask Ada for a token');
});

it('matches a report against the neighbours on whole words only', function () {
    $neighbours = app(Neighbours::class);

    expect($neighbours->match('Finding a customer signup')['key'])->toBe('crm')
        ->and($neighbours->match('reordering the chapters of a study'))->toBeNull()
        ->and($neighbours->match('the invoicer broke'))->toBeNull();
});

it('says where it may belong before a gap is filed, and files it anyway when told to', function () {
    $user = actingWith(acmeUser(), ['acme:things:read'], 'laptop');

    $arguments = [
        'title' => 'cannot see a customer\'s invoices',
        'need' => 'Sjekke fakturaene til en kunde.',
        'missing' => 'No tool lists invoices for a signup.',
    ];

    AcmeServer::actingAs($user)->tool(ReportGapTool::class, $arguments)
        ->assertOk()
        ->assertSee('Acme CRM')
        ->assertSee('search_contacts')
        ->assertSee('ask Ada for a token');

    AcmeServer::actingAs($user)->tool(ReportGapTool::class, $arguments + ['confirm' => true])->assertOk();

    expect(app(GapStore::class)->list())->toHaveCount(1);
});
