<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Tools;

use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Uploads\Uploads;
use Illuminate\Support\Facades\URL;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * An upload link for an assistant that cannot send the bearer token.
 *
 * Behind a connector (Claude, ChatGPT, Codex with the token in its config)
 * the assistant talks MCP through a token it never sees, so it cannot POST
 * a file with that token. This signs a short-lived link bound to the same
 * token instead: the assistant POSTs the file there, gets the usual `up_…`
 * handle back, and passes it on. Ownership, size and type limits, the access
 * gate and the expiry of the staged file are exactly those of a bearer
 * upload. Creates nothing — the file arrives, or the link runs out.
 */
#[IsReadOnly]
class RequestUploadTool extends Tool
{
    protected string $name = 'request_upload';

    protected string $description = 'A short-lived link to upload a local file without the bearer token — for when your token sits in the connector settings and you cannot send it. POST the file to the link as multipart field `file` (the reply gives a curl line to copy), then pass the returned `up_…` handle as `upload` to a tool that takes files. One link takes several files until it expires. Needs a way to send an HTTP request from where the file is, such as a shell.';

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

        if (! $principal->token instanceof PersonalAccessToken) {
            return Response::error('An upload link is bound to the token you are using, and this request did not come with one.');
        }

        $minutes = max(1, (int) config('mcp-kit.uploads.link_minutes', 30));
        $expires = now()->addMinutes($minutes);

        // The token rather than the person: revoking the token kills the link.
        $url = URL::temporarySignedRoute('mcp-kit.uploads.link', $expires, ['token' => $principal->token->getKey()]);

        $mimes = (array) config('mcp-kit.uploads.mimes', []);

        return Response::json([
            'url' => $url,
            'method' => 'POST',
            'field' => 'file',
            'curl' => "curl -sS -H 'Accept: application/json' -F 'file=@/path/to/file' '{$url}'",
            'expires_at' => $expires->toIso8601String(),
            'max_kb' => (int) config('mcp-kit.uploads.max_kb', 51200),
            'accepts' => $mimes === [] ? 'any file type' : $mimes,
            'reply' => 'The POST answers with `upload` (the up_… handle), the name, the size and a sha256 checksum. Compare the checksum with the local file to be sure the bytes arrived whole.',
            'next' => 'Pass the handle as `upload` to the tool that takes the file. Staged files expire after '.app(Uploads::class)->ttlDays().' days; list_uploads shows what is there.',
            'keep_it_private' => 'Anyone holding this link can stage files in your name until it expires. Use it, do not paste it anywhere else.',
        ]);
    }

    public function shouldRegister(): bool
    {
        return (bool) config('mcp-kit.uploads.enabled', false);
    }
}
