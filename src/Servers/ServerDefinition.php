<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Servers;

use Laravel\Mcp\Server;

/**
 * One entry of mcp-kit.servers.
 */
final class ServerDefinition
{
    /**
     * @param  class-string<Server>  $class
     * @param  list<string>  $openAbilities
     */
    public function __construct(
        public readonly string $key,
        public readonly string $class,
        public readonly string $path,
        public readonly string $label,
        public readonly ?string $wildcard,
        public readonly string $clientName,
        public readonly bool $requiresStaff,
        public readonly bool $serviceClients,
        public readonly bool $presets,
        public readonly bool $shared,
        public readonly string $color,
        public readonly string $icon,
        public readonly string $description,
        public readonly ?string $aliasOf = null,
        public readonly array $openAbilities = [],
        public readonly bool $listGrantedOnly = false,
        /** Image the client shows for this server: a URL, or a path under public/. */
        public readonly ?string $avatar = null,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $key, array $config): self
    {
        return new self(
            key: $key,
            class: (string) ($config['class'] ?? ''),
            path: '/'.ltrim((string) ($config['path'] ?? "mcp/{$key}"), '/'),
            label: (string) ($config['label'] ?? ucfirst($key)),
            wildcard: isset($config['wildcard']) ? (string) $config['wildcard'] : null,
            clientName: (string) ($config['client_name'] ?? $key),
            requiresStaff: (bool) ($config['requires_staff'] ?? true),
            serviceClients: (bool) ($config['service_clients'] ?? true),
            presets: (bool) ($config['presets'] ?? true),
            shared: (bool) ($config['shared'] ?? true),
            color: (string) ($config['color'] ?? 'zinc'),
            icon: (string) ($config['icon'] ?? 'wrench-screwdriver'),
            description: (string) ($config['description'] ?? ''),
            aliasOf: isset($config['alias_of']) ? (string) $config['alias_of'] : null,
            openAbilities: array_values(array_map('strval', (array) ($config['open_abilities'] ?? []))),
            listGrantedOnly: (bool) ($config['list_granted_only'] ?? false),
            avatar: isset($config['avatar']) ? (string) $config['avatar'] : (config('mcp-kit.avatar') !== null ? (string) config('mcp-kit.avatar') : null),
        );
    }

    /**
     * The server this route answers for. An alias entry keeps an old path
     * alive after a merge: it serves the target's class, and every access
     * decision is made as if the caller had hit the target's own route.
     */
    public function effectiveKey(): string
    {
        return $this->aliasOf ?? $this->key;
    }

    /**
     * Whether a person must be staff to use this ability. A staff server can
     * open named abilities to people who are not staff (a practice owner on
     * the same server as the back office); everything else keeps the
     * server's own rule. Patterns may end in `*`.
     */
    public function abilityNeedsStaff(string $ability): bool
    {
        return $this->requiresStaff && ! $this->isOpen($ability);
    }

    /**
     * A person who is not staff passes the door of a staff server only when
     * their token carries at least one ability the server opened to them.
     *
     * @param  list<string>  $abilities
     */
    public function admitsNonStaff(array $abilities): bool
    {
        if (! $this->requiresStaff) {
            return true;
        }

        foreach ($abilities as $ability) {
            if ($this->isOpen($ability)) {
                return true;
            }
        }

        return false;
    }

    protected function isOpen(string $ability): bool
    {
        foreach ($this->openAbilities as $pattern) {
            if ($pattern === $ability || (str_ends_with($pattern, '*') && str_starts_with($ability, substr($pattern, 0, -1)))) {
                return true;
            }
        }

        return false;
    }

    public function routeName(): string
    {
        return "mcp-kit.{$this->key}";
    }

    public function url(): string
    {
        return url($this->path);
    }

    public function uri(): string
    {
        return ltrim($this->path, '/');
    }
}
