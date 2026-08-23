<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Servers;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Routing\Route;

/**
 * The configured servers, in display order. Reads config every time so
 * tests that change mcp-kit.servers see the change.
 */
class ServerRegistry
{
    public function __construct(protected Repository $config) {}

    /**
     * @return array<string, ServerDefinition>
     */
    public function all(): array
    {
        $servers = [];

        foreach ((array) $this->config->get('mcp-kit.servers', []) as $key => $definition) {
            if (is_array($definition) && ($definition['class'] ?? '') !== '') {
                $servers[(string) $key] = ServerDefinition::fromConfig((string) $key, $definition);
            }
        }

        return $servers;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function firstKey(): ?string
    {
        return $this->keys()[0] ?? null;
    }

    public function get(string $key): ?ServerDefinition
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @param  class-string  $class
     */
    public function forClass(string $class): ?ServerDefinition
    {
        foreach ($this->all() as $definition) {
            if ($definition->class === $class || is_subclass_of($class, $definition->class)) {
                return $definition;
            }
        }

        return null;
    }

    public function forRoute(Route|string|null $route): ?ServerDefinition
    {
        if ($route === null) {
            return null;
        }

        $name = $route instanceof Route ? $route->getName() : $route;

        if (is_string($name) && str_starts_with($name, 'mcp-kit.')) {
            return $this->get(substr($name, strlen('mcp-kit.')));
        }

        $uri = $route instanceof Route ? $route->uri() : $route;

        foreach ($this->all() as $definition) {
            if ($definition->uri() === ltrim((string) $uri, '/')) {
                return $definition;
            }
        }

        return null;
    }
}
