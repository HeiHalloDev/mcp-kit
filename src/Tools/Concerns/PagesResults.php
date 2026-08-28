<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tools\Concerns;

use Illuminate\Contracts\Database\Query\Builder;
use Laravel\Mcp\Request;

/**
 * Paging and sorting for list tools.
 *
 * A sweep of six apps found 62 tools that cap their results and five that
 * let you past the cap: the rest ordered one fixed way with no offset, so
 * anything beyond the limit was unreachable by any route. An analyst
 * hunting the earliest-enrolled contacts in a sequence ended up grepping
 * an old session transcript to find one.
 *
 * The reply always carries `total` and `has_more`, because a full page
 * looking identical to a complete answer is the part that misleads.
 */
trait PagesResults
{
    /**
     * Merge into a tool's `$inputSchema` properties. `sort` is deliberately
     * absent: each tool declares its own allowed values.
     */
    public const PAGING_PROPERTIES = [
        'offset' => [
            'type' => 'integer',
            'description' => 'Skip this many rows before returning any — page past the limit. The reply says how many matched altogether.',
            'default' => 0,
            'minimum' => 0,
        ],
        'direction' => [
            'type' => 'string',
            'enum' => ['asc', 'desc'],
            'description' => 'Order direction. Defaults to whatever the tool considers natural — oldest-first needs asc.',
        ],
    ];

    /**
     * Apply limit, offset and (when asked for) ordering, and describe what
     * was left out.
     *
     * The tool's own ordering is kept unless the caller actually asks for a
     * sort or a direction: several of these order by more than one column
     * on purpose.
     *
     * @param  array<string, string>  $sorts  sort value => column
     * @return array{total: int, offset: int, limit: int, returned: int, has_more: bool}
     */
    protected function applyPaging(
        Builder $query,
        Request $request,
        array $sorts = [],
        ?string $defaultSort = null,
        string $defaultDirection = 'desc',
        int $defaultLimit = 20,
        int $maxLimit = 50,
    ): array {
        $total = (clone $query)->count();

        $limit = min(max($request->integer('limit', $defaultLimit), 1), $maxLimit);
        $offset = max($request->integer('offset', 0), 0);

        $askedSort = (string) $request->string('sort');
        $askedDirection = (string) $request->string('direction');
        $direction = in_array($askedDirection, ['asc', 'desc'], true) ? $askedDirection : $defaultDirection;

        if ($askedSort !== '' || $askedDirection !== '') {
            $column = $sorts[$askedSort] ?? ($defaultSort !== null ? ($sorts[$defaultSort] ?? null) : null);

            if ($column !== null) {
                $query->reorder()->orderBy($column, $direction);
            }
        }

        $query->offset($offset)->limit($limit);

        return [
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'returned' => min(max($total - $offset, 0), $limit),
            'has_more' => $offset + $limit < $total,
        ];
    }
}
