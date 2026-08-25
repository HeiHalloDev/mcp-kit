<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Servers;

use HeiHallo\McpKit\Contracts\GroundRules;
use HeiHallo\McpKit\Mcp\Tools\WhoAmITool;
use HeiHallo\McpKit\Playbooks\PlaybookPrompts;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\ServerContext;

/**
 * Base class for every staff-facing server. Subclasses list their tools,
 * resources and prompts as properties (the registry and the docs read the
 * defaults without instantiating); boot() composes the instructions with
 * the kit's footer, appends the shared primitives and applies read-only
 * mode.
 */
abstract class StaffServer extends Server
{
    public int $maxPaginationLength = 100;

    public int $defaultPaginationLength = 100;

    protected function boot(): void
    {
        $definition = app(ServerRegistry::class)->forClass(static::class);

        if ($definition?->shared ?? true) {
            $this->appendShared();
            $this->appendPlaybooks($definition?->key);
        }

        if (config('mcp-kit.read_only', false)) {
            $this->tools = ReadOnlyFilter::apply($this->tools);
        }
    }

    public function createContext(): ServerContext
    {
        $context = parent::createContext();
        $definition = app(ServerRegistry::class)->forClass(static::class);
        $authored = $this->resolveAttribute(Instructions::class)?->value ?? $this->instructions;

        $context->instructions = app(GroundRules::class)->instructions($definition?->key ?? '', $authored);

        return $context;
    }

    /**
     * The caller's own saved playbooks, as prompts. Built per request, so
     * two people on the same server see different lists.
     */
    protected function appendPlaybooks(?string $server): void
    {
        foreach (app(PlaybookPrompts::class)->for($server) as $prompt) {
            $this->prompts[] = $prompt;
        }
    }

    protected function appendShared(): void
    {
        foreach ((array) config('mcp-kit.shared.resources', []) as $resource) {
            $this->appendUnique($this->resources, $resource);
        }

        foreach ((array) config('mcp-kit.shared.tools', []) as $tool) {
            $this->appendUnique($this->tools, $tool);
        }

        if (config('mcp-kit.me.expose_as_tool', false)) {
            $this->appendUnique($this->tools, WhoAmITool::class);
        }

        foreach ((array) config('mcp-kit.shared.prompts', []) as $prompt) {
            $this->appendUnique($this->prompts, $prompt);
        }
    }

    /**
     * @param  array<int|string, mixed>  $list
     */
    private function appendUnique(array &$list, mixed $item): void
    {
        foreach ($list as $existing) {
            if ($existing === $item || (is_array($existing) && in_array($item, $existing, true))) {
                return;
            }
        }

        $list[] = $item;
    }
}
