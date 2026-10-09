<?php

declare(strict_types=1);

use HeiHallo\McpKit\Testing\Mcp;

/*
 * Clients that list tools and never resources or prompts (claude.ai
 * connectors used from Claude Code) still reach everything the app serves:
 * each shared resource and prompt has a tool that answers with the same text.
 */

function toolText(string $token, string $tool, array $arguments = []): string
{
    return (string) Mcp::call($token, '/mcp/acme', $tool, $arguments)->assertSuccessful()->json('result.content.0.text');
}

test('the developer views are tools for privileged callers only, with the resources\' text', function () {
    config()->set('mcp-kit.learning.enabled', true);
    $admin = acmeToken(acmeAdmin(), ['acme:things:read']);

    Mcp::listTools($admin, '/mcp/acme')->assertSee('list_gaps')->assertSee('get_usage');

    expect(toolText($admin, 'list_gaps'))
        ->toBe((string) Mcp::readResource($admin, '/mcp/acme', 'acme://gaps')->json('result.contents.0.text'))
        // The record counts the call that reads it, so the same sections, not the same bytes.
        ->and(toolText($admin, 'get_usage'))->toContain('## Reading this')
        ->and(toolText($admin, 'get_usage', ['person' => 'Ada']))->toContain('Ada Admin');

    $staff = acmeToken(acmeUser(), ['acme:things:read']);

    Mcp::listTools($staff, '/mcp/acme')->assertDontSee('list_gaps')->assertDontSee('get_usage');
});

test('a saved playbook is listed and run through tools, arguments filled in', function () {
    $user = acmeUser();
    $token = acmeToken($user, ['acme:things:read']);

    Mcp::call($token, '/mcp/acme', 'save_playbook', [
        'name' => 'weekly digest',
        'description' => 'The numbers I send out on Mondays.',
        'body' => "1. Read the reports for {{ week }}.\n2. Write the three lines that changed.",
        'arguments' => [['name' => 'week', 'description' => 'Which week', 'required' => true]],
        'confirm' => true,
    ]);

    expect(toolText($token, 'list_playbooks'))
        ->toBe((string) Mcp::readResource($token, '/mcp/acme', 'acme://playbooks')->json('result.contents.0.text'))
        ->toContain('weekly');

    $steps = toolText($token, 'run_playbook', ['name' => 'weekly_digest', 'arguments' => ['week' => 'week 34']]);

    expect($steps)->toContain('week 34')->not->toContain('{{ week }}');

    Mcp::call($token, '/mcp/acme', 'run_playbook', ['name' => 'weekly_digest'])->assertSee('needs week');
    Mcp::call($token, '/mcp/acme', 'run_playbook', ['name' => 'nobody_saved_this'])->assertSee('list_playbooks');
});

test('getting_started is a tool too', function () {
    $token = acmeToken(acmeUser(), ['acme:things:read']);

    expect(toolText($token, 'getting_started', ['focus' => 'the weekly numbers']))
        ->toContain('the weekly numbers');
});

test('resource_tools off leaves only the resources and prompts', function () {
    config()->set('mcp-kit.resource_tools', false);
    config()->set('mcp-kit.learning.enabled', true);

    Mcp::listTools(acmeToken(acmeAdmin(), ['acme:things:read']), '/mcp/acme')
        ->assertDontSee('list_gaps')->assertDontSee('list_playbooks')->assertDontSee('run_playbook')->assertDontSee('"getting_started"', false);
});
