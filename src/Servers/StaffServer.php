<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Servers;

use HeiHallo\McpKit\Contracts\GroundRules;
use HeiHallo\McpKit\Mcp\Methods\ListGrantedTools;
use HeiHallo\McpKit\Mcp\Prompts\GettingStartedPrompt;
use HeiHallo\McpKit\Mcp\Resources\GapsResource;
use HeiHallo\McpKit\Mcp\Resources\PlaybooksResource;
use HeiHallo\McpKit\Mcp\Resources\UsagePersonResource;
use HeiHallo\McpKit\Mcp\Resources\UsageResource;
use HeiHallo\McpKit\Mcp\Tools\GetGroundRulesTool;
use HeiHallo\McpKit\Mcp\Tools\GettingStartedTool;
use HeiHallo\McpKit\Mcp\Tools\GetUsageTool;
use HeiHallo\McpKit\Mcp\Tools\ListGapsTool;
use HeiHallo\McpKit\Mcp\Tools\ListPlaybooksTool;
use HeiHallo\McpKit\Mcp\Tools\RunPlaybookTool;
use HeiHallo\McpKit\Mcp\Tools\WhoAmITool;
use HeiHallo\McpKit\Playbooks\PlaybookPrompts;
use HeiHallo\McpKit\Tools\ToolAppearances;
use Laravel\Mcp\Schema\Icon;
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

        // Before read-only mode hides anything, so switching it off and on
        // never makes old tools look new.
        if ($definition !== null) {
            app(ToolAppearances::class)->record($definition->key, array_map(
                fn (mixed $tool): string => is_object($tool) ? $tool::class : (string) $tool,
                $this->tools,
            ));
        }

        if (config('mcp-kit.read_only', false)) {
            $this->tools = ReadOnlyFilter::apply($this->tools);
        }

        if ($definition?->listGrantedOnly) {
            $this->addMethod('tools/list', ListGrantedTools::class);
        }
    }

    /**
     * The server's avatar, sent in serverInfo.icons (MCP 2025-11-25) so a
     * client can show it next to the connection. A server class that sets
     * its own #[Icon] keeps it; this comes after.
     *
     * @return list<Icon>
     */
    protected function icons(): array
    {
        $avatar = app(ServerRegistry::class)->forClass(static::class)?->avatar;

        if ($avatar === null || trim($avatar) === '') {
            return [];
        }

        $mime = match (strtolower(pathinfo((string) parse_url($avatar, PHP_URL_PATH), PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => null,
        };

        return [Icon::from($avatar, $mime, $mime === 'image/svg+xml' ? ['any'] : [])];
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

            // Its per-person twin rides along, so apps that published the
            // shared list before it existed get it too.
            if ($resource === UsageResource::class) {
                $this->appendUnique($this->resources, UsagePersonResource::class);
            }
        }

        foreach ((array) config('mcp-kit.shared.tools', []) as $tool) {
            $this->appendUnique($this->tools, $tool);
        }

        // Resources as tools: some clients list tools and never resources.
        // me.expose_as_tool is the older switch for whoami alone.
        if (config('mcp-kit.resource_tools', true) || config('mcp-kit.me.expose_as_tool', false)) {
            $this->appendUnique($this->tools, WhoAmITool::class);
        }

        if (config('mcp-kit.resource_tools', true)) {
            $this->appendUnique($this->tools, GetGroundRulesTool::class);

            // The rest of the shared resources and prompts, each as a tool,
            // where the app serves the resource or prompt in the first place.
            $resources = (array) config('mcp-kit.shared.resources', []);
            $prompts = (array) config('mcp-kit.shared.prompts', []);
            $mirrors = [
                ListGapsTool::class => in_array(GapsResource::class, $resources, true),
                GetUsageTool::class => in_array(UsageResource::class, $resources, true),
                ListPlaybooksTool::class => in_array(PlaybooksResource::class, $resources, true),
                RunPlaybookTool::class => in_array(PlaybooksResource::class, $resources, true),
                GettingStartedTool::class => in_array(GettingStartedPrompt::class, $prompts, true),
            ];

            foreach ($mirrors as $tool => $served) {
                if ($served) {
                    $this->appendUnique($this->tools, $tool);
                }
            }
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
