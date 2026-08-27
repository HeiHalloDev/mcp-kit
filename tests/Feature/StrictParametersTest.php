<?php

declare(strict_types=1);

use HeiHallo\McpKit\Models\ServiceClient;
use HeiHallo\McpKit\Testing\Mcp;

/*
 * An argument a tool does not declare is dropped by every $request->get()
 * that follows, and the tool then answers about something else. The CRM's
 * get_available_slots, handed `user_id`, ignored it, fell back to the
 * caller and reported that *they* had no booking calendar — the wrong
 * person, for the wrong reason, with no sign anything had gone astray.
 */

test('an argument the tool does not declare is refused, with the real name', function () {
    $token = acmeToken(acmeUser(), ['acme:things:read']);

    Mcp::call($token, '/mcp/acme', 'list_things', ['querry' => 'kari'])
        ->assertSee('`querry` is not a parameter here — did you mean `query`?')
        ->assertSee('Nothing was done')
        ->assertSee('Parameters: query');
});

test('a name nowhere near a real one is refused without a wrong guess', function () {
    $token = acmeToken(acmeUser(), ['acme:things:read']);

    $body = Mcp::call($token, '/mcp/acme', 'list_things', ['organisational_unit' => 'x'])->getContent();

    expect($body)->toContain('`organisational_unit` is not a parameter here.')
        ->and($body)->not->toContain('did you mean');
});

test('the declared parameters still work untouched', function () {
    $token = acmeToken(acmeUser(), ['acme:things:read']);

    Mcp::call($token, '/mcp/acme', 'list_things', ['query' => 'kari'])->assertOk()->assertDontSee('not a parameter');
    Mcp::call($token, '/mcp/acme', 'list_things')->assertOk()->assertDontSee('not a parameter');
});

test('a service client is exempt — its calls are code, not a model guessing', function () {
    $client = Mcp::token(
        ServiceClient::query()->create(['name' => 'Flex', 'slug' => 'flex']),
        ['acme:things:read'],
        'service',
    );

    Mcp::call($client, '/mcp/acme', 'list_things', ['legacy_field' => 'x'])
        ->assertOk()
        ->assertDontSee('not a parameter');
});

test('the switch turns it off', function () {
    config()->set('mcp-kit.strict_parameters', false);

    $token = acmeToken(acmeUser(), ['acme:things:read']);

    Mcp::call($token, '/mcp/acme', 'list_things', ['querry' => 'kari'])->assertOk()->assertDontSee('not a parameter');
});

test('an ability denial still wins — a stray argument never leaks the schema', function () {
    // A real ability, but not the one this tool checks.
    $token = acmeToken(acmeUser(), ['acme:events:write']);

    Mcp::call($token, '/mcp/acme', 'list_things', ['querry' => 'kari'])
        ->assertSee('acme:things:read')
        ->assertDontSee('Parameters:');
});

test('a field the tool turns away on purpose gives its own reason, not a typo guess', function () {
    $token = acmeToken(acmeUser(), ['acme:things:write']);

    // UpdateThingTool declares `owner` as refused: identity lives in
    // another service, and "did you mean `name`?" would be a worse answer
    // than saying so.
    Mcp::call($token, '/mcp/acme', 'update_thing', ['id' => 1, 'owner' => 'kari'])
        ->assertSee('Owner is owned by the directory and cannot be changed here.')
        ->assertDontSee('did you mean')
        ->assertDontSee('Parameters:');
});
