<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Resources;

use HeiHallo\McpKit\Contracts\GapStore;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Gaps\Gap;
use HeiHallo\McpKit\Servers\ServerRegistry;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

/**
 * The reported gaps, for whoever builds this app. Staff report; reading
 * the list and deciding what to do about it is a developer's job, so it
 * is privileged — a narrow token may belong to somebody outside the team
 * entirely, and what a colleague said about their own work is not theirs
 * to read.
 */
class GapsResource extends Resource
{
    protected string $name = 'gaps';

    protected string $description = 'For whoever builds this app: what people have reported it cannot do yet, most-wanted first, with who asked and what was decided about the ones already closed. Privileged staff only.';

    protected string $mimeType = 'text/markdown';

    public function uri(): string
    {
        return config('mcp-kit.scheme', 'app').'://gaps';
    }

    public function shouldRegister(): bool
    {
        return (bool) config('mcp-kit.gaps.enabled', true);
    }

    public function handle(Request $request): Response
    {
        $tokenable = $request->user();
        $principal = $tokenable ? app(PrincipalResolver::class)->resolve($tokenable) : null;

        if ($principal === null) {
            return Response::error('Authentication required.');
        }

        if (! $principal->privileged) {
            return Response::error('The gap list is for whoever builds this app. You can report one with report_gap; reading what everybody reported is a developer\'s job.');
        }

        $store = app(GapStore::class);

        return Response::text(view('mcp-kit::resources.gaps', [
            'principal' => $principal,
            'open' => $store->list([Gap::OPEN, Gap::PLANNED]),
            'closed' => $store->list([Gap::DONE, Gap::DECLINED], limit: 10),
            'servers' => app(ServerRegistry::class)->keys(),
        ])->render());
    }
}
