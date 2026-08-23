<?php

declare(strict_types=1);

use HeiHallo\McpKit\Mcp\Prompts\GettingStartedPrompt;
use HeiHallo\McpKit\Mcp\Tools\RememberAboutMeTool;
use HeiHallo\McpKit\Models\ServiceClient;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers\AcmeServer;

test('someone with role and team is asked at most day, handoffs and tone', function () {
    $team = acmeTeam();
    $user = actingWith(acmeUser(['staff', 'things'], 'staff', ['team_id' => $team->id]), ['acme:*']);

    $response = AcmeServer::actingAs($user)->prompt(GettingStartedPrompt::class)->assertOk();

    $response->assertSee('- role: staff')
        ->assertSee('- team: Support')
        ->assertSee('1. What does a normal day look like')
        ->assertSee('2. Who do you hand things to')
        ->assertSee('3. When something goes out to customers')
        ->assertDontSee('What is your role')
        ->assertDontSee('Which team or area')
        ->assertSee('Rename a thing, preview first.')
        ->assertSee('onboarding=completed');
});

test('someone the app knows nothing about is asked about role and team within the cap', function () {
    config()->set('mcp-kit.onboarding.max_questions', 5);
    $user = actingWith(acmeUser(['staff', 'things'], null), ['acme:things:read']);

    $response = AcmeServer::actingAs($user)->prompt(GettingStartedPrompt::class)->assertOk();

    $response->assertSee('What is your role')
        ->assertSee('Which team or area')
        ->assertDontSee('When something goes out to customers')
        ->assertSee('This token is read-only');

    config()->set('mcp-kit.onboarding.max_questions', 2);

    AcmeServer::actingAs($user)->prompt(GettingStartedPrompt::class)->assertOk()
        ->assertSee('1. What does a normal day')
        ->assertSee('2. Who do you hand')
        ->assertDontSee('What is your role');
});

test('someone with memory is asked nothing and goes straight to suggestions', function () {
    $user = actingWith(acmeUser(), ['acme:*']);

    AcmeServer::actingAs($user)->tool(RememberAboutMeTool::class, [
        'team' => 'Support',
        'routines' => ['Inbox first'],
        'handoffs' => ['Invoices to Ola'],
        'preferences' => ['language' => 'nb'],
        'confirm' => true,
    ]);

    AcmeServer::actingAs($user)->prompt(GettingStartedPrompt::class, ['focus' => 'clean up old things'])->assertOk()
        ->assertSee('## Nothing to ask')
        ->assertSee('- routines: Inbox first')
        ->assertSee('- today: clean up old things')
        ->assertSee('three things to try that fit today')
        ->assertDontSee('1. What does');
});

test('suggestions only cover what the token holds, with a fallback when none match', function () {
    $user = actingWith(acmeUser(['staff', 'things', 'reports']), ['reports:read']);

    AcmeServer::actingAs($user)->prompt(GettingStartedPrompt::class)->assertOk()
        ->assertSee('Ask for the monthly numbers.')
        ->assertDontSee('Rename a thing');

    config()->set('mcp-kit.suggestions', []);

    AcmeServer::actingAs($user)->prompt(GettingStartedPrompt::class)->assertOk()
        ->assertSee('Pick one read-only thing');
});

test('a service client is refused', function () {
    $client = ServiceClient::query()->create(['name' => 'Flex', 'slug' => 'flex']);
    $client = $client->withAccessToken($client->createToken('service', ['acme:*'])->accessToken);

    AcmeServer::actingAs($client)->prompt(GettingStartedPrompt::class)->assertHasErrors()->assertSee('for people');
});
