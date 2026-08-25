<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Playbooks;

use DateTimeInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * One saved way of working: a recipe the person discovered once and wants
 * back next time. Stored per person, surfaced as an MCP prompt so clients
 * show it the way they show any other prompt.
 */
final class Playbook
{
    /**
     * @param  list<array{name: string, description: string, required: bool}>  $arguments
     * @param  list<string>  $servers
     * @param  list<string>  $abilities
     */
    public function __construct(
        public readonly string $name,
        public readonly string $title,
        public readonly string $description,
        public readonly string $body,
        public readonly array $arguments = [],
        public readonly array $servers = [],
        public readonly array $abilities = [],
        public readonly bool $shared = false,
        public readonly int|string|null $userId = null,
        public readonly ?string $author = null,
        public readonly int $uses = 0,
        public readonly ?DateTimeInterface $lastUsedAt = null,
    ) {}

    /**
     * A prompt name a client can show: lowercase, words joined by
     * underscores, never colliding with the kit's own prompts.
     */
    public static function slug(string $name): string
    {
        $slug = Str::snake(Str::ascii(trim($name)), '_');
        $slug = trim((string) preg_replace('/[^a-z0-9_]+/', '_', strtolower($slug)), '_');
        $slug = (string) preg_replace('/_+/', '_', $slug);

        if ($slug === '') {
            throw new InvalidArgumentException('A playbook needs a name made of letters or numbers.');
        }

        return Str::limit($slug, 60, '');
    }

    /**
     * The placeholders the body actually uses, as {{ name }}.
     *
     * @return list<string>
     */
    public function placeholders(): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $this->body, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * The body with the caller's arguments filled in. A placeholder with no
     * value is left visible rather than silently blanked, so the assistant
     * can ask for it.
     *
     * @param  array<string, mixed>  $values
     */
    public function render(array $values): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            function (array $match) use ($values): string {
                $value = $values[$match[1]] ?? null;

                return is_scalar($value) && (string) $value !== ''
                    ? (string) $value
                    : $match[0];
            },
            $this->body,
        );
    }

    /**
     * Does this playbook belong on the given server?
     */
    public function appliesTo(?string $server): bool
    {
        return $this->servers === [] || $server === null || in_array($server, $this->servers, true);
    }

    /**
     * @param  list<string>  $granted  Expanded token abilities.
     */
    public function runnableWith(array $granted): bool
    {
        foreach ($this->abilities as $ability) {
            if (! in_array($ability, $granted, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'title' => $this->title,
            'description' => $this->description,
            'body' => $this->body,
            'arguments' => $this->arguments,
            'servers' => $this->servers,
            'abilities' => $this->abilities,
            'shared' => $this->shared,
        ];
    }
}
