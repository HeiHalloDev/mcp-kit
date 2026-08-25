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
 * What people have already said this app cannot do. Read before filing
 * anything: a gap that is already open wants another voice behind it,
 * not a second row.
 */
class GapsResource extends Resource
{
    protected string $name = 'gaps';

    protected string $description = 'What people have reported that this app cannot do yet, most-wanted first, with what was decided about the ones already closed. Read this before report_gap so the same gap gathers weight instead of duplicating.';

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

        $store = app(GapStore::class);

        return Response::text(view('mcp-kit::resources.gaps', [
            'principal' => $principal,
            'open' => $store->list([Gap::OPEN, Gap::PLANNED]),
            'closed' => $store->list([Gap::DONE, Gap::DECLINED], limit: 10),
            'servers' => app(ServerRegistry::class)->keys(),
        ])->render());
    }
}
