<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Playbooks;

use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Servers\ServerRegistry;
use InvalidArgumentException;

/**
 * Turns what the assistant passed to save_playbook into a Playbook, or
 * says why it will not: too long, an unknown server, an ability the
 * catalogue does not have, a placeholder with no argument behind it.
 */
class PlaybookFactory
{
    public function __construct(
        protected PlaybookLimits $limits,
        protected AbilityCatalogue $catalogue,
        protected ServerRegistry $servers,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function make(array $input, ?Playbook $existing = null): Playbook
    {
        $name = Playbook::slug((string) ($input['name'] ?? $existing?->name ?? ''));
        $body = $this->text($input, 'body', $existing?->body ?? '', $this->limits->bodyChars, 'The steps');

        if ($body === '') {
            throw new InvalidArgumentException('A playbook needs a body: the steps to follow, in the order they are done.');
        }

        $title = $this->text($input, 'title', $existing?->title ?? '', $this->limits->titleChars, 'The title');
        $description = $this->text($input, 'description', $existing?->description ?? '', $this->limits->descriptionChars, 'The description');

        if ($description === '') {
            throw new InvalidArgumentException('A playbook needs a one-line description: it is what the person sees in the list.');
        }

        $playbook = new Playbook(
            name: $name,
            title: $title === '' ? $this->titleFrom($name) : $title,
            description: $description,
            body: $body,
            arguments: $this->arguments($input, $existing),
            servers: $this->servers($input, $existing),
            abilities: $this->abilities($input, $existing),
            shared: array_key_exists('shared', $input)
                ? filter_var($input['shared'], FILTER_VALIDATE_BOOL)
                : ($existing?->shared ?? false),
            userId: $existing?->userId,
        );

        $this->assertPlaceholdersDeclared($playbook);

        return $playbook;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function text(array $input, string $key, string $fallback, int $max, string $label): string
    {
        $value = array_key_exists($key, $input) ? trim((string) $input[$key]) : $fallback;

        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException(sprintf('%s is %d characters; the limit is %d.', $label, mb_strlen($value), $max));
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{name: string, description: string, required: bool}>
     */
    protected function arguments(array $input, ?Playbook $existing): array
    {
        if (! array_key_exists('arguments', $input)) {
            return $existing?->arguments ?? [];
        }

        $arguments = [];

        foreach ((array) $input['arguments'] as $argument) {
            $argument = is_string($argument) ? ['name' => $argument] : (array) $argument;
            $name = Playbook::slug((string) ($argument['name'] ?? ''));

            $arguments[$name] = [
                'name' => $name,
                'description' => trim((string) ($argument['description'] ?? '')),
                'required' => filter_var($argument['required'] ?? false, FILTER_VALIDATE_BOOL),
            ];
        }

        if (count($arguments) > $this->limits->arguments) {
            throw new InvalidArgumentException(sprintf('A playbook takes at most %d arguments.', $this->limits->arguments));
        }

        return array_values($arguments);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    protected function servers(array $input, ?Playbook $existing): array
    {
        if (! array_key_exists('servers', $input)) {
            return $existing?->servers ?? [];
        }

        $known = $this->servers->keys();
        $servers = [];

        foreach ((array) $input['servers'] as $server) {
            $server = (string) $server;

            if (! in_array($server, $known, true)) {
                throw new InvalidArgumentException(sprintf(
                    "There is no server called '%s'. This app has: %s.",
                    $server,
                    $known === [] ? 'none' : implode(', ', $known),
                ));
            }

            $servers[] = $server;
        }

        return array_values(array_unique($servers));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    protected function abilities(array $input, ?Playbook $existing): array
    {
        if (! array_key_exists('abilities', $input)) {
            return $existing?->abilities ?? [];
        }

        $abilities = [];

        foreach ((array) $input['abilities'] as $ability) {
            $ability = $this->catalogue->canonical((string) $ability);

            if (! $this->catalogue->exists($ability)) {
                throw new InvalidArgumentException("There is no ability called '{$ability}'.");
            }

            $abilities[] = $ability;
        }

        return array_values(array_unique($abilities));
    }

    /**
     * A placeholder nobody declared would silently stay in the text every
     * time the playbook runs, so say so while the person is still here.
     */
    protected function assertPlaceholdersDeclared(Playbook $playbook): void
    {
        $declared = array_column($playbook->arguments, 'name');
        $missing = array_values(array_diff($playbook->placeholders(), $declared));

        if ($missing !== []) {
            throw new InvalidArgumentException(sprintf(
                'The body uses %s, but %s not in the arguments. Add %s, or take the placeholder out.',
                implode(', ', array_map(static fn (string $name): string => '{{ '.$name.' }}', $missing)),
                count($missing) === 1 ? 'it is' : 'they are',
                count($missing) === 1 ? 'it' : 'them',
            ));
        }
    }

    protected function titleFrom(string $name): string
    {
        return ucfirst(str_replace('_', ' ', $name));
    }
}
