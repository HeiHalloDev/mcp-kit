<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Resources;

use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\PlaybookStore;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Playbooks\Playbook;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

/**
 * What this person has saved, and what colleagues shared. Clients that
 * list prompts already show the runnable ones; this is where the
 * assistant reads the steps without running anything.
 */
class PlaybooksResource extends Resource
{
    protected string $name = 'playbooks';

    protected string $description = 'The ways of working this person saved, plus the ones colleagues shared: what each does, what it needs, and the steps. Read it before offering to save something that already exists.';

    protected string $mimeType = 'text/markdown';

    public function uri(): string
    {
        return config('mcp-kit.scheme', 'app').'://playbooks';
    }

    public function shouldRegister(): bool
    {
        return (bool) config('mcp-kit.playbooks.enabled', true);
    }

    public function handle(Request $request): Response
    {
        $tokenable = $request->user();
        $principal = $tokenable ? app(PrincipalResolver::class)->resolve($tokenable) : null;

        if ($principal === null) {
            return Response::error('Authentication required.');
        }

        if ($principal->isService()) {
            return Response::error('Playbooks belong to people: a service client has none.');
        }

        $granted = app(AbilityCatalogue::class)->expand($principal->abilities());
        $playbooks = app(PlaybookStore::class)->visibleTo($tokenable);

        $mine = array_values(array_filter(
            $playbooks,
            fn (Playbook $p): bool => (string) $p->userId === (string) $principal->id(),
        ));

        $theirs = array_values(array_filter(
            $playbooks,
            fn (Playbook $p): bool => (string) $p->userId !== (string) $principal->id(),
        ));

        return Response::text(view('mcp-kit::resources.playbooks', [
            'principal' => $principal,
            'mine' => $mine,
            'theirs' => $theirs,
            'granted' => $granted,
            'prefix' => (string) config('mcp-kit.playbooks.prefix', ''),
        ])->render());
    }
}
