<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Console\Commands;

use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\PresetResolver;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Contracts\TokenPolicy;
use HeiHallo\McpKit\Exceptions\TokenRefused;
use HeiHallo\McpKit\Tokens\ClientSetup;
use HeiHallo\McpKit\Tokens\ConnectSnippets;
use HeiHallo\McpKit\Tokens\TokenMinter;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Mint a personal MCP token. Tokens are per person so every tool call stays
 * attributable; abilities are capped by the person's permissions and
 * re-checked on every request.
 */
class TokenCommand extends Command
{
    protected $signature = 'mcp:token
                            {email : The person the token belongs to}
                            {--name=claude-code : Token name, e.g. the device it lives on}
                            {--preset= : A preset from config/mcp-kit.php (token_presets), filtered by the person\'s permissions}
                            {--abilities= : Comma-separated abilities instead of a preset}
                            {--expires= : Days until the token expires (default and max in mcp-kit.tokens)}
                            {--revoke : Revoke every kit token for this person instead of creating one}';

    protected $description = 'Mint a personal MCP token — tokens are per person so actions stay attributable';

    public function handle(
        TokenMinter $minter,
        PresetResolver $presets,
        AbilityCatalogue $catalogue,
        PrincipalResolver $principals,
        TokenPolicy $policy,
        ConnectSnippets $snippets,
    ): int {
        $user = $this->findUser((string) $this->argument('email'));

        if ($user === null) {
            $this->error("No user with e-mail {$this->argument('email')}.");

            return self::FAILURE;
        }

        $principal = $principals->resolve($user);

        if ($this->option('revoke')) {
            $count = $minter->revokeAll($user);
            $this->info("Revoked {$count} MCP token(s) for {$principal?->name}.");

            return self::SUCCESS;
        }

        $preset = (string) $this->option('preset');
        $raw = (string) $this->option('abilities');

        if ($preset !== '' && $raw !== '') {
            $this->error('Give either --preset or --abilities, not both.');

            return self::FAILURE;
        }

        if ($preset === '' && $raw === '') {
            $preset = $policy->defaultPreset();
        }

        if ($preset !== '') {
            if (! array_key_exists($preset, $presets->all())) {
                $this->error("Unknown preset '{$preset}'. Choose: ".implode(', ', array_keys($presets->all())).'.');

                return self::FAILURE;
            }

            $abilities = $presets->abilitiesFor($user, $preset);

            if ($abilities === []) {
                $this->error("The '{$preset}' preset resolves to nothing for {$principal?->name} — check the user's role and permissions.");

                return self::FAILURE;
            }
        } else {
            $abilities = array_values(array_filter(array_map('trim', explode(',', $raw))));
        }

        $days = $this->option('expires') !== null && $this->option('expires') !== ''
            ? (int) $this->option('expires')
            : ($preset !== '' ? $presets->expiresDaysFor($preset) : null);

        try {
            $token = $minter->mint($user, (string) $this->option('name'), $abilities, $days, preset: $preset !== '' ? $preset : null);
        } catch (TokenRefused $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Token created for {$principal?->name} ({$token->accessToken->name}):");
        $this->newLine();
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->components->warn('Copy it now — it is not shown again.');
        $this->line('Abilities: '.implode(', ', $token->accessToken->abilities));
        $this->line('Expires: '.($token->accessToken->expires_at?->toDateString() ?? 'never'));
        $this->newLine();
        $this->line('Claude Code:');

        $lines = $snippets->claudeCode($catalogue->serversFor($token->accessToken->abilities), $token->plainTextToken);

        foreach (explode("\n", $lines) as $line) {
            if ($line !== '') {
                $this->line('  '.$line);
            }
        }

        $this->newLine();
        $this->line('No claude command yet? '.ClientSetup::claudeCode()['install'][__('macOS, Linux, WSL')]);
        $this->line('Other clients, and the same lines with a copy button, are on the tokens page.');

        return self::SUCCESS;
    }

    protected function findUser(string $email): ?Authenticatable
    {
        $model = (string) config('auth.providers.users.model');

        return $model::query()->where('email', $email)->first();
    }
}
