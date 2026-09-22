<?php

declare(strict_types=1);

use HeiHallo\McpKit\Livewire\TokensPage;
use HeiHallo\McpKit\McpKitServiceProvider;
use HeiHallo\McpKit\Tokens\ClientSetup;
use Livewire\Livewire;

/*
 * The connect lines assume a client that is already installed. The people
 * minting these tokens were told an assistant could read the CRM; they
 * were not told to install anything, and a page that hands them
 * `claude mcp add …` without saying where `claude` comes from stops there.
 */

test('every client says what it is, where to get it, and how to install it', function () {
    foreach (ClientSetup::all() as $key => $client) {
        expect($client['title'])->not->toBe('', "[{$key}] has no title.")
            ->and($client['summary'])->not->toBe('', "[{$key}] says nothing about what it is.")
            ->and($client['install'])->not->toBe([], "[{$key}] offers no way to install it.")
            ->and($client['docs']['url'])->toStartWith('https://', "[{$key}] links its documentation over something other than https.");

        if ($client['download'] !== null) {
            expect($client['download']['url'])->toStartWith('https://', "[{$key}] links its download over something other than https.");
        }

        foreach ($client['install'] as $platform => $command) {
            expect($platform)->toBeString()->not->toBe('')
                ->and($command)->toBeString()->not->toBe('');
        }
    }
});

test('the install lines are the vendors own, not a guess', function () {
    $claudeCode = ClientSetup::claudeCode();
    $codex = ClientSetup::codex();

    expect($claudeCode['install'])->toContain('curl -fsSL https://claude.ai/install.sh | bash')
        ->and($claudeCode['install'])->toContain('npm install -g @anthropic-ai/claude-code')
        ->and($claudeCode['verify'])->toBe('claude --version')
        ->and($codex['install'])->toContain('npm install -g @openai/codex')
        ->and(ClientSetup::claudeDesktop()['download']['url'])->toBe('https://claude.com/download');
});

test('the connect tab shows where to get the client next to the line it asks you to paste', function () {
    config()->set('mcp-kit.ui.enabled', true);
    config()->set('mcp-kit.ui.tokens_page.enabled', true);
    $provider = new McpKitServiceProvider(app());
    (fn () => $this->registerUi())->call($provider);

    $this->actingAs(acmeAdmin());

    Livewire::test(TokensPage::class)
        ->assertSee('claude mcp add acme ')
        ->assertSee('curl -fsSL https://claude.ai/install.sh | bash')
        ->assertSee('claude --version')
        ->assertSee('https://claude.com/download')
        ->assertSee('npm install -g @openai/codex')
        ->assertSee('https://code.claude.com/docs/en/setup');
});

test('the Claude app is told the one-paste route, because its Code tab reads the CLI connection', function () {
    expect(ClientSetup::claudeDesktop()['summary'])->toContain('claude mcp add');
});
