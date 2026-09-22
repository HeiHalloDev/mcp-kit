<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Neighbours;

use HeiHallo\McpKit\Principal;
use Illuminate\Contracts\Cache\Repository as Cache;
use Throwable;

/**
 * The hint an assistant gets when it is doing something the hard way.
 *
 * Reading a study one page at a time took 434 calls before anybody noticed
 * there was a tool that read the whole branch. Checking twenty people
 * against the CRM took ninety single lookups. In both cases the better tool
 * existed and was listed; nothing said so at the moment it would have
 * helped, and a tool list is long.
 *
 * So the kit counts how often one tool is called in a stretch of work and,
 * past a threshold the app sets, says the sentence in the reply the
 * assistant is already reading. It is a nudge, not a refusal: the call it
 * arrives with has already been answered.
 */
final class Hints
{
    public function __construct(private readonly Cache $cache) {}

    /**
     * Count this call and return the hint when it is due.
     */
    public function forCall(?Principal $principal, ?string $tool, ?string $frame): ?string
    {
        $hint = $this->configFor($tool);
        $key = $this->key($principal, $tool, $frame);

        if ($hint === null || $key === null) {
            return null;
        }

        $after = max(2, (int) ($hint['after'] ?? config('mcp-kit.hints.after', 5)));
        $every = max(0, (int) ($hint['repeat_every'] ?? config('mcp-kit.hints.repeat_every', 15)));

        try {
            $calls = $this->cache->add($key, 1, $this->ttl()) ? 1 : (int) $this->cache->increment($key);
        } catch (Throwable) {
            return null;
        }

        $due = $calls === $after || ($every > 0 && $calls > $after && ($calls - $after) % $every === 0);

        if (! $due) {
            return null;
        }

        return sprintf(
            'That is %d calls to %s in this piece of work. %s',
            $calls,
            $tool,
            $this->sentence($hint),
        );
    }

    /**
     * @param  array<string, mixed>  $hint
     */
    private function sentence(array $hint): string
    {
        $say = trim((string) ($hint['say'] ?? ''));
        $use = trim((string) ($hint['use'] ?? ''));

        if ($use === '') {
            return $say;
        }

        return rtrim('`'.$use.'` '.$say, '.').'. Nothing is wrong with the call you just made — this is only the shorter road.';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function configFor(?string $tool): ?array
    {
        if ($tool === null || ! config('mcp-kit.hints.enabled', true)) {
            return null;
        }

        $hints = (array) config('mcp-kit.hints.instead_of', []);
        $hint = $hints[$tool] ?? null;

        return is_array($hint) && $hint !== [] ? $hint : null;
    }

    /**
     * Counted per stretch of work when frames are on, and per token
     * otherwise, so an app without the learning record still gets hints.
     */
    private function key(?Principal $principal, ?string $tool, ?string $frame): ?string
    {
        $token = $principal?->tokenId();

        if ($token === null || $tool === null) {
            return null;
        }

        return 'mcp-kit:hints:'.($frame ?? 'token:'.$token).':'.$tool;
    }

    private function ttl(): int
    {
        return max(600, (int) config('mcp-kit.learning.lifetime_hours', 4) * 3600);
    }
}
