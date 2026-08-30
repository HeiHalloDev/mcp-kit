<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tools\Concerns;

use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Models\Upload;
use HeiHallo\McpKit\Uploads\Uploads;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Request;

/**
 * Lets a tool take a staged file by handle.
 *
 * MCP carries JSON, not bytes: the file went up over HTTP first with the
 * same token, and the tool receives the handle. Declare the parameter by
 * merging UPLOAD_PROPERTY into the schema, then:
 *
 *     $upload = $this->stagedUpload($request);
 *     if (is_string($upload)) { return Response::error($upload); }
 *     // ... copy $this->stagedPath($upload) into the app's real home ...
 *     $this->uploadConsumed($upload, target: $paper->handle());
 */
trait AcceptsUploads
{
    /**
     * Merge into `$inputSchema['properties']` under the key `upload`.
     */
    public const UPLOAD_PROPERTY = [
        'type' => 'string',
        'description' => 'Handle of a staged file (`up_…`) from the upload endpoint. Files go up over HTTP with your same bearer token; list_uploads shows what is staged.',
    ];

    /**
     * The staged file, ownership checked — or a sentence saying why not.
     */
    protected function stagedUpload(Request $request, string $parameter = 'upload'): Upload|string
    {
        $handle = trim((string) $request->get($parameter, ''));

        if ($handle === '') {
            return 'Pass `'.$parameter.'` with the handle of a staged file (`up_…`). Upload one first — POST the file to the upload endpoint with your same bearer token.';
        }

        $principal = app(PrincipalResolver::class)->resolve($request->user());

        if ($principal === null) {
            return 'Authentication required.';
        }

        return app(Uploads::class)->resolve($handle, $principal);
    }

    /**
     * Absolute path of the staged bytes, for copying into the real home.
     */
    protected function stagedPath(Upload $upload): string
    {
        return Storage::disk($upload->disk)->path($upload->path);
    }

    /**
     * Say the tool took its copy, and where it went.
     */
    protected function uploadConsumed(Upload $upload, ?string $target = null): void
    {
        app(Uploads::class)->consumed($upload, method_exists($this, 'name') ? $this->name() : static::class, $target);
    }
}
