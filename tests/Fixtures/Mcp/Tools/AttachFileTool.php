<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools;

use HeiHallo\McpKit\Tests\Fixtures\Models\Thing;
use HeiHallo\McpKit\Tools\StaffTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * The consuming side of an upload: takes a staged handle, reads the bytes,
 * records the consumption. What a real app tool does with AcceptsUploads.
 */
class AttachFileTool extends StaffTool
{
    protected string $name = 'attach_file';

    protected string $description = 'Attach a staged file to a thing.';

    /**
     * @var array<string, mixed>
     */
    protected array $inputSchema = [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Thing id'],
            'upload' => self::UPLOAD_PROPERTY,
        ],
        'required' => ['id', 'upload'],
    ];

    public function handle(Request $request): Response
    {
        if ($denied = $this->requireAbility($request, 'acme:things:write')) {
            return $denied;
        }

        $thing = Thing::query()->find($request->integer('id'));

        if ($thing === null) {
            return Response::error('No thing matches id.');
        }

        $upload = $this->stagedUpload($request);

        if (is_string($upload)) {
            return Response::error($upload);
        }

        $contents = file_get_contents($this->stagedPath($upload));

        $this->uploadConsumed($upload, target: 'thing#'.$thing->id);

        return Response::json([
            'attached' => $upload->name,
            'to' => $thing->name,
            'bytes' => strlen((string) $contents),
        ]);
    }
}
