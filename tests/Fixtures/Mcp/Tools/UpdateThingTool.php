<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools;

use HeiHallo\McpKit\Tests\Fixtures\Models\Thing;
use HeiHallo\McpKit\Tools\StaffTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use RuntimeException;

#[IsIdempotent]
class UpdateThingTool extends StaffTool
{
    protected string $name = 'update_thing';

    protected string $description = 'Rename a thing. Previews without confirm=true.';

    protected array $inputSchema = [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
            'fail' => ['type' => 'string', 'description' => 'Harness: "runtime" or "boom" to simulate failures'],
            'confirm' => ['type' => 'boolean'],
        ],
        'required' => ['id', 'name'],
    ];

    public function handle(Request $request): Response
    {
        if ($denied = $this->requireAbility($request, 'acme:things:write')) {
            return $denied;
        }

        $thing = Thing::query()->findOrFail((int) $request->get('id'));

        return $this->previewOrExecute(
            $request,
            ['thing' => $this->withAdminUrl(['id' => $thing->id, 'from' => $thing->name, 'to' => $request->get('name')], $thing)],
            function () use ($thing, $request): array {
                if ($request->get('fail') === 'runtime') {
                    throw new RuntimeException('The thing is locked.');
                }

                if ($request->get('fail') === 'boom') {
                    throw new \LogicException('boom');
                }

                $thing->update(['name' => $request->get('name')]);

                activity('things')->performedOn($thing)->log('renamed');

                return ['thing' => ['id' => $thing->id, 'name' => $thing->name]];
            },
            'Rename thing',
            ['subject' => $thing, 'exception_messages' => [\LogicException::class => 'Mapped: something went wrong']],
        );
    }
}
