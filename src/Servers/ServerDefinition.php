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
