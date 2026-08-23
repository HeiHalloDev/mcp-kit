<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Onboarding;

use HeiHallo\McpKit\Contracts\SuggestsTasks;
use HeiHallo\McpKit\Principal;

/**
 * mcp-kit.suggestions: ability => list of things to try. Filtered to what
 * the token holds; three picked with a seed that changes weekly, so the
 * same person sees the same three all week and new ones next week.
 */
class ConfigSuggestions implements SuggestsTasks
{
    public const FALLBACK = 'Pick one read-only thing that matches what the person does today, and show how the tools answer it.';

    public function suggestions(Principal $principal, array $abilities): array
    {
        $map = (array) config('mcp-kit.suggestions', []);
        $pool = [];

        foreach ($map as $ability => $lines) {
            if (in_array((string) $ability, $abilities, true)) {
                foreach ((array) $lines as $line) {
                    $pool[] = (string) $line;
                }
            }
        }

        $pool = array_values(array_unique($pool));

        if ($pool === []) {
            return [self::FALLBACK];
        }

        $seed = crc32((string) $principal->id().'|'.now()->format('o-W'));
        mt_srand($seed);
        $keys = array_keys($pool);
        shuffle($keys);
        mt_srand();

        return array_map(static fn (int $key): string => $pool[$key], array_slice($keys, 0, 3));
    }
}
