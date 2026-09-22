<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Neighbours;

use Illuminate\Support\Str;

/**
 * The connections next door, and what each of them owns.
 *
 * An assistant that cannot do something here has no way of knowing whether
 * the job is impossible or simply somebody else's: it gives up, or works
 * around it for two hundred calls. A real case — a content person went
 * looking for students' homework in the material server, found nothing, and
 * read the student web in a browser. The tool he needed was one connection
 * away, and he already had it.
 *
 * So each app says who its neighbours are and what they hold. The kit puts
 * that in every server's instructions, and matches a gap report against it
 * before filing, so the answer arrives while the person is still asking.
 */
final class Neighbours
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $neighbours = [];

        foreach ((array) config('mcp-kit.neighbours', []) as $key => $neighbour) {
            if (! is_array($neighbour) || trim((string) ($neighbour['owns'] ?? '')) === '') {
                continue;
            }

            $neighbours[(string) $key] = [
                'label' => (string) ($neighbour['label'] ?? Str::headline((string) $key)),
                'owns' => trim((string) $neighbour['owns']),
                'tools' => array_values(array_filter(array_map('strval', (array) ($neighbour['tools'] ?? [])))),
                'match' => array_values(array_filter(array_map(
                    static fn ($word): string => mb_strtolower(trim((string) $word)),
                    (array) ($neighbour['match'] ?? []),
                ))),
                'url' => trim((string) ($neighbour['url'] ?? '')),
                'ask' => trim((string) ($neighbour['ask'] ?? '')),
            ];
        }

        return $neighbours;
    }

    public function any(): bool
    {
        return $this->all() !== [];
    }

    /**
     * The section every server carries: what this app does not hold, and
     * who does. Written once here rather than in ten apps' prose.
     */
    public function instructions(): ?string
    {
        $neighbours = $this->all();

        if ($neighbours === []) {
            return null;
        }

        $lines = ['## What is not here', '', 'Some of what people ask for belongs to another connection. Say which one and what it holds, rather than working around it or reaching for a browser.', ''];

        foreach ($neighbours as $neighbour) {
            $line = '- **'.$neighbour['label'].'** — '.rtrim($neighbour['owns'], '.').'.';

            if ($neighbour['tools'] !== []) {
                $line .= ' Tools there: `'.implode('`, `', array_slice($neighbour['tools'], 0, 8)).'`.';
            }

            $line .= $this->howToGetIn($neighbour);
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * The neighbour a piece of text points at, if any: the one whose words
     * appear in it. Whole words, both ends — "invoice" must not match
     * "invoicer", and "lead" must not match "leader".
     *
     * Nothing is stemmed, because stemming guesses. A word ending in `*`
     * says plainly that whatever follows is still the same word, which is
     * how Norwegian is written: `karakter*` is what catches
     * karakterfordeling, `innlevering*` catches studentinnleveringene, and
     * an app that wants the plural of an English word can simply list it.
     *
     * @return array<string, mixed>|null
     */
    public function match(string ...$text): ?array
    {
        $haystack = mb_strtolower(implode(' ', array_filter($text)));

        if (trim($haystack) === '') {
            return null;
        }

        $best = null;
        $hits = 0;

        foreach ($this->all() as $key => $neighbour) {
            $found = 0;

            foreach ($neighbour['match'] as $word) {
                if ($word !== '' && preg_match($this->pattern($word), $haystack)) {
                    $found++;
                }
            }

            if ($found > $hits) {
                $hits = $found;
                $best = ['key' => $key] + $neighbour;
            }
        }

        return $best;
    }

    /**
     * A word to look for: bounded at both ends, unless it ends in `*`, in
     * which case it only has to start a word.
     */
    private function pattern(string $word): string
    {
        $open = str_ends_with($word, '*');
        $word = $open ? rtrim($word, '*') : $word;

        return '/(?<![\p{L}\p{N}])'.preg_quote($word, '/').($open ? '' : '(?![\p{L}\p{N}])').'/u';
    }

    /**
     * One sentence for a tool reply: where it lives, what to call, and how
     * to get in if the person has no connection to it yet.
     *
     * @param  array<string, mixed>  $neighbour
     */
    public function hint(array $neighbour): string
    {
        $hint = 'This may belong to the '.$neighbour['label'].' connection, which holds '.rtrim((string) $neighbour['owns'], '.').'.';

        if ($neighbour['tools'] !== []) {
            $hint .= ' Look for `'.implode('`, `', array_slice((array) $neighbour['tools'], 0, 6)).'`.';
        }

        return $hint.$this->howToGetIn($neighbour);
    }

    /**
     * How somebody without that connection gets it. Staff mint their own
     * tokens in each app, so the answer is an address, not a person to
     * wait for — a hint that ends in "ask somebody" is a hint that ends.
     *
     * @param  array<string, mixed>  $neighbour
     */
    private function howToGetIn(array $neighbour): string
    {
        $directions = '';

        if (($neighbour['url'] ?? '') !== '') {
            $directions .= ' Already connected? Then the tools are in this same conversation. If not, mint yourself a token at '.$neighbour['url'].' and add the connection.';
        }

        if (($neighbour['ask'] ?? '') !== '') {
            $directions .= ' '.$neighbour['ask'];
        }

        return $directions;
    }
}
