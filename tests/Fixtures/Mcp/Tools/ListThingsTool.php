<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools;

use HeiHallo\McpKit\Tests\Fixtures\Models\Thing;
use HeiHallo\McpKit\Tools\StaffTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListThingsTool extends StaffTool
{
    protected string $name = 'list_things';

    protected string $description = 'List things. Optionally filter by name.';

    protected array $inputSchema = [
        'type' => 'object',
        'properties' => [
            'query' => ['type' => 'string', 'description' => 'Part of a name'],
            'sort' => ['type' => 'string', 'enum' => ['name', 'created_at'], 'description' => 'What to order by'],
            'limit' => ['type' => 'integer', 'description' => 'Maximum rows (max 50)', 'default' => 20, 'minimum' => 1, 'maximum' => 50],
            ...self::PAGING_PROPERTIES,
        ],
    ];

    public function handle(Request $request): Response
    {
        if ($denied = $this->requireAbility($request, 'acme:things:read')) {
            return $denied;
        }

        $builder = Thing::query()
            ->when($request->get('query'), fn ($q, $query) => $q->where('name', 'ilike', "%{$query}%"))
            ->orderBy('name');

        $paging = $this->applyPaging($builder, $request, [
            'name' => 'name',
            'created_at' => 'created_at',
        ], defaultSort: 'name', defaultDirection: 'asc');

        $things = $builder->get()
            ->map(fn (Thing $thing): array => $this->withAdminUrl(['id' => $thing->id, 'name' => $thing->name], $thing))
            ->all();

        return Response::json($this->withListAdminUrl(['things' => $things] + $paging, 'things', ['query' => $request->get('query')]));
    }
}
