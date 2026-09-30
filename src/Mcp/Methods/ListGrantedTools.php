<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Methods;

use HeiHallo\McpKit\Docs\ToolReference;
use HeiHallo\McpKit\Tools\AbilityProbe;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Methods\ListTools;
use Laravel\Mcp\Server\Pagination\CursorPaginator;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

/**
 * tools/list for a server with `list_granted_only`: a caller is shown the
 * tools their token can actually use. A practice owner on the same server
 * as the back office sees their own tools, not sixty they would be refused.
 *
 * Only the list is filtered. tools/call still resolves every tool, so a
 * call to one that was not listed gets the kit's refusal naming the ability
 * — not "tool not found", which reads like a typo and hides the reason.
 * A tool with no ability in its source (who_am_i, working_on) is shown to
 * everyone.
 */
class ListGrantedTools extends ListTools
{
    /** @var array<class-string, list<string>> */
    protected static array $abilities = [];

    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $probe = new AbilityProbe;
        $caller = new Request;

        $tools = $context->tools()->filter(function (Tool $tool) use ($probe, $caller): bool {
            $abilities = static::$abilities[$tool::class] ??= array_values(array_filter(
                app(ToolReference::class)->abilitiesFor($tool::class),
                static fn (string $ability): bool => ! str_contains($ability, '*'),
            ));

            if ($abilities === []) {
                return true;
            }

            foreach ($abilities as $ability) {
                if ($probe->allows($caller, $ability)) {
                    return true;
                }
            }

            return false;
        })->values();

        $paginator = new CursorPaginator(
            items: $tools,
            perPage: $context->perPage($request->get('per_page')),
            cursor: $request->cursor(),
        );

        return JsonRpcResponse::result($request->id, $paginator->paginate('tools'));
    }
}
