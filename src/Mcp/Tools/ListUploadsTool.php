<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Tools;

use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Models\Upload;
use HeiHallo\McpKit\Uploads\Uploads;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Your own staging area, from inside MCP. Bytes go up over HTTP; this is
 * how a session — including one that replaced a cut-off session — sees
 * what is already there instead of uploading again.
 */
#[IsReadOnly]
#[IsIdempotent]
class ListUploadsTool extends Tool
{
    protected string $name = 'list_uploads';

    protected string $description = 'Your staged files: what you have uploaded over HTTP and not yet lost to the clock. Shows each handle, its checksum (compare it to be sure a re-upload matches), what consumed it, and when it expires. Staged files are a loading dock — a tool has to copy one into a real home before it ages out.';

    /**
     * @var array<string, mixed>
     */
    protected array $inputSchema = [
        'type' => 'object',
        'properties' => [],
    ];

    public function handle(Request $request): Response
    {
        $principal = app(PrincipalResolver::class)->resolve($request->user());

        if ($principal === null) {
            return Response::error('Authentication required.');
        }

        $uploads = app(Uploads::class);
        $staged = $uploads->for($principal);

        if ($staged === []) {
            return Response::text('Nothing staged. POST a file to the upload endpoint with your bearer token, or to a link from request_upload, and it shows up here with a handle.');
        }

        return Response::json([
            'count' => count($staged),
            'ttl_days' => $uploads->ttlDays(),
            'uploads' => array_map(fn (Upload $u): array => [
                'upload' => $u->handle,
                'name' => $u->name,
                'mime' => $u->mime,
                'size' => $u->size,
                'checksum' => $u->checksum,
                'uploaded_at' => $u->created_at?->toIso8601String(),
                'expires_at' => $u->expires_at->toIso8601String(),
                'consumed' => $u->consumed ?? [],
            ], $staged),
        ]);
    }

    public function shouldRegister(): bool
    {
        return (bool) config('mcp-kit.uploads.enabled', false);
    }
}
