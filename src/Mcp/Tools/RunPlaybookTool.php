<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Tools;

use HeiHallo\McpKit\Contracts\PlaybookStore;
use HeiHallo\McpKit\Mcp\Prompts\PlaybookPrompt;
use HeiHallo\McpKit\Playbooks\Playbook;
use HeiHallo\McpKit\Tools\StaffTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * A saved playbook, run as a tool: the same steps its prompt hands out,
 * for clients that do not offer prompts. Registered with
 * mcp-kit.resource_tools.
 */
class RunPlaybookTool extends StaffTool
{
    protected string $name = 'run_playbook';

    protected string $description = 'Get the steps of a saved playbook, filled in with its arguments, then follow them. Names come from list_playbooks. The same as choosing the playbook as a prompt.';

    /** @var array<string, mixed> */
    protected array $inputSchema = [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string', 'description' => 'The playbook name from list_playbooks.'],
            'arguments' => ['type' => 'object', 'description' => 'The playbook\'s arguments by name, if it takes any.'],
        ],
        'required' => ['name'],
    ];

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            return Response::error('Authentication required.');
        }

        $prefix = (string) config('mcp-kit.playbooks.prefix', '');
        $name = (string) $request->get('name', '');
        $name = $prefix !== '' && str_starts_with($name, $prefix) ? substr($name, strlen($prefix)) : $name;

        $playbook = collect(app(PlaybookStore::class)->visibleTo($user))
            ->first(fn (Playbook $p): bool => $p->name === $name);

        if ($playbook === null) {
            return Response::error("No playbook called {$name} that you can see. list_playbooks shows the ones there are.");
        }

        $arguments = (array) $request->get('arguments', []);

        return (new PlaybookPrompt($playbook))->handle((clone $request)->merge($arguments));
    }

    public function shouldRegister(): bool
    {
        return (bool) config('mcp-kit.playbooks.enabled', true);
    }
}
