<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\GapStore;
use HeiHallo\McpKit\Contracts\GroundRules;
use HeiHallo\McpKit\Mcp\Tools\ReportGapTool;
use HeiHallo\McpKit\Neighbours\Neighbours;
use HeiHallo\McpKit\Testing\Mcp;
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
            'match' => ['customer', 'kunde', 'signup', 'invoice', 'faktura*'],
            'url' => 'https://crm.example.test/settings/tokens',
            'ask' => 'Ada knows which abilities you need.',
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
        ->toContain('https://crm.example.test/settings/tokens')
        ->toContain('mint yourself a token');
});

it('matches a report against the neighbours on whole words only', function () {
    $neighbours = app(Neighbours::class);

    expect($neighbours->match('Finding a customer signup')['key'])->toBe('crm')
        ->and($neighbours->match('reordering the chapters of a study'))->toBeNull()
        ->and($neighbours->match('the invoicer broke'))->toBeNull()
        // Norwegian glues its words together, so a word can say that
        // whatever follows it belongs to it.
        ->and($neighbours->match('fakturagrunnlaget mangler')['key'])->toBe('crm')
        ->and($neighbours->match('en gammel faktura')['key'])->toBe('crm');
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
        ->assertSee('crm.example.test/settings/tokens');

    AcmeServer::actingAs($user)->tool(ReportGapTool::class, $arguments + ['confirm' => true])->assertOk();

    expect(app(GapStore::class)->list())->toHaveCount(1);
});

it('says where it lives when a call about it is refused', function () {
    $token = acmeToken(acmeUser(), ['acme:things:read']);

    $refused = (string) Mcp::call($token, '/mcp/acme', 'list_things', ['query' => str_repeat('x', 300)])->getContent();

    expect($refused)->not->toContain('Acme CRM');

    $aboutTheirs = (string) Mcp::call($token, '/mcp/acme', 'update_thing', ['id' => '999999', 'name' => 'the customer invoice'])->getContent();

    expect($aboutTheirs)->toContain('Acme CRM')
        ->and($aboutTheirs)->toContain('search_contacts');
});

it('leaves a successful call alone, and stays quiet when the app turns it off', function () {
    $token = acmeToken(acmeUser(), ['acme:things:read']);

    expect((string) Mcp::call($token, '/mcp/acme', 'list_things', ['query' => 'customer'])->getContent())
        ->not->toContain('Acme CRM');

    config()->set('mcp-kit.neighbours_on_refusal', false);

    expect((string) Mcp::call($token, '/mcp/acme', 'update_thing', ['id' => '999999', 'name' => 'the customer invoice'])->getContent())
        ->not->toContain('Acme CRM');
});
