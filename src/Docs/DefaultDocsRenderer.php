<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Docs;

use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\DocsRenderer;
use HeiHallo\McpKit\Servers\ServerRegistry;

/**
 * The default shape of the generated blocks: a numbered tool summary, a
 * write-tools table, and an ability matrix. Swap it through
 * mcp-kit.docs.renderer for a different layout.
 */
class DefaultDocsRenderer implements DocsRenderer
{
    public function __construct(
        protected ServerRegistry $servers,
        protected AbilityCatalogue $catalogue,
    ) {}

    public function toolsBlock(array $tools): string
    {
        $writes = array_values(array_filter($tools, static fn (array $tool): bool => $tool['writes']));
        $serviceWrites = array_map('strval', (array) config('mcp-kit.catalogue.service_client_writes', []));

        $lines = [];
        $lines[] = '## Tool summary (generated)';
        $lines[] = '';
        $lines[] = sprintf(
            '%d tools across %d server%s. %d read, %d write. Every tool checks a token ability (see `config/mcp-kit.php`) before executing.',
            count($tools),
            count($this->servers->all()),
            count($this->servers->all()) === 1 ? '' : 's',
            count($tools) - count($writes),
            count($writes),
        );
        $lines[] = '';
        $lines[] = '| # | Tool | Server | Domain | Ability | Type |';
        $lines[] = '|---|------|--------|--------|---------|------|';

        foreach ($tools as $i => $tool) {
            $lines[] = sprintf(
                '| %d | `%s` | %s | %s | `%s` | %s |',
                $i + 1,
                $tool['name'],
                $tool['server'],
                $tool['domain'],
                $tool['ability'] ?? '—',
                $tool['writes'] ? '**Write**' : 'Read',
            );
        }

        $lines[] = '';
        $lines[] = '## Write tools (generated)';
        $lines[] = '';
        $lines[] = 'These modify data. Unless noted, each previews without `confirm=true` and executes with it.'
            .($serviceWrites === [] ? ' Service-client tokens are refused on every write.' : ' Service-client tokens are refused on every write except those behind '.implode(', ', array_map(static fn (string $a): string => "`{$a}`", $serviceWrites)).'.');
        $lines[] = '';
        $lines[] = '| Tool | Server | Ability | Annotations | What it does |';
        $lines[] = '|------|--------|---------|-------------|--------------|';

        foreach ($writes as $tool) {
            $lines[] = sprintf(
                '| `%s` | %s | `%s` | %s | %s |',
                $tool['name'],
                $tool['server'],
                $tool['ability'] ?? '—',
                $tool['annotations'] === [] ? '—' : implode(', ', $tool['annotations']),
                static::firstSentence($tool['description']),
            );
        }

        return implode("\n", $lines)."\n";
    }

    public function abilitiesBlock(): string
    {
        $lines = ['| Ability | Server | Requires (any of) | Grants |', '|---|---|---|---|'];

        foreach ($this->catalogue->all() as $ability => $description) {
            $permissions = $this->catalogue->requiredPermissions($ability);

            $lines[] = sprintf(
                '| `%s` | %s | %s | %s |',
                $ability,
                $this->catalogue->isWildcard($ability) ? implode(', ', $this->catalogue->serversFor([$ability])) : ($this->catalogue->serverFor($ability) ?? '—'),
                $permissions === [] ? 'any user' : implode(' or ', $permissions),
                $description,
            );
        }

        $explicit = $this->catalogue->explicitOnly();

        if ($explicit !== []) {
            $lines[] = '';
            $lines[] = 'Explicit-only abilities — never granted by a wildcard, privileged owners only:';
            $lines[] = '';
            $lines[] = '| Ability | Requires | Grants |';
            $lines[] = '|---|---|---|';

            foreach ($explicit as $ability => $description) {
                $permissions = $this->catalogue->requiredPermissions($ability);
                $lines[] = sprintf('| `%s` | %s | %s |', $ability, $permissions === [] ? 'any user' : implode(' or ', $permissions), $description);
            }
        }

        $aliases = (array) config('mcp-kit.catalogue.aliases', []);

        if ($aliases !== []) {
            $lines[] = '';
            $lines[] = 'Legacy names still honoured by the tools (never minted, flagged by `mcp:audit-tokens`):';
            $lines[] = '';
            $lines[] = '| Legacy | Resolves to |';
            $lines[] = '|---|---|';

            foreach ($aliases as $legacy => $canonical) {
                $lines[] = sprintf('| `%s` | `%s` |', $legacy, $canonical);
            }
        }

        return implode("\n", $lines)."\n";
    }

    public static function firstSentence(string $description): string
    {
        $description = trim(preg_replace('/\s+/', ' ', $description) ?? '');
        $cut = strpos($description, '. ');

        return $cut === false ? $description : substr($description, 0, $cut + 1);
    }
}
