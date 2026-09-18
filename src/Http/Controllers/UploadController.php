<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Http\Controllers;

use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Events\FileStaged;
use HeiHallo\McpKit\Uploads\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The upload endpoint next to the MCP routes: same bearer token, same
 * access gate (EnsureMcpAccess runs in front of this). A request_upload
 * link reaches it too, with the link standing in for the bearer header. Deliberately dumb —
 * it stages bytes and answers with a handle; every meaning the file gains
 * comes later, from the tool that consumes it.
 */
class UploadController
{
    public function __invoke(Request $request, Uploads $uploads, PrincipalResolver $principals): JsonResponse
    {
        $principal = $principals->resolve($request->user());

        if ($principal === null) {
            return response()->json(['error' => 'Authentication required.'], 401);
        }

        $file = $request->file('file');

        if ($file === null || ! $file->isValid()) {
            return response()->json(['error' => 'Send the file as a multipart field named `file`.'], 422);
        }

        $maxKb = (int) config('mcp-kit.uploads.max_kb', 51200);

        if ($file->getSize() > $maxKb * 1024) {
            return response()->json(['error' => sprintf('Too big: %d KB, and this app accepts %d KB. Nothing was stored.', intdiv((int) $file->getSize(), 1024), $maxKb)], 413);
        }

        $mimes = (array) config('mcp-kit.uploads.mimes', []);

        if ($mimes !== [] && ! in_array($file->getClientMimeType(), $mimes, true)) {
            return response()->json(['error' => sprintf('`%s` is not accepted here. This app takes: %s. Nothing was stored.', $file->getClientMimeType(), implode(', ', $mimes))], 422);
        }

        $upload = $uploads->stage($principal, $file);

        event(new FileStaged($upload, $principal));

        return response()->json([
            'upload' => $upload->handle,
            'name' => $upload->name,
            'mime' => $upload->mime,
            'size' => $upload->size,
            'checksum' => $upload->checksum,
            'expires_at' => $upload->expires_at->toIso8601String(),
            'next' => 'Pass this handle as the `upload` parameter of a tool that accepts files, or see it again with list_uploads. It expires — staged files are a loading dock, not a home.',
        ], 201);
    }
}
