<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Http\Middleware;

use Closure;
use HeiHallo\McpKit\Audit\CallRecord;
use HeiHallo\McpKit\Audit\McpCallContext;
use HeiHallo\McpKit\Audit\Sanitizer;
use HeiHallo\McpKit\Contracts\AuditWriter;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Events\ToolCallRecorded;
use HeiHallo\McpKit\Servers\ServerRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Opens the call context before the server runs and records the call after:
 * who, which tool, sanitised arguments, duration, outcome. Only the methods
 * in mcp-kit.activity.log_methods (tools/call) become rows; the rest of the
 * protocol chatter does not.
 */
class AuditMcpCall
{
    public function __construct(
        protected McpCallContext $context,
        protected PrincipalResolver $principals,
        protected ServerRegistry $servers,
        protected AuditWriter $audit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $parsed = $this->parse($request);

        if ($parsed === null || ! in_array($parsed['method'], (array) config('mcp-kit.activity.log_methods', ['tools/call']), true)) {
            return $next($request);
        }

        $tokenable = $request->user();
        $principal = $tokenable ? $this->principals->resolve($tokenable) : null;
        $requestId = $request->header('X-Request-ID') ?: (string) Str::uuid();

        $this->context->begin(
            method: $parsed['method'],
            tool: $parsed['tool'],
            arguments: $parsed['arguments'],
            principal: $principal,
            server: $this->servers->forRoute($request->route())?->key,
            client: McpCallContext::clientFrom($request),
            requestId: $requestId,
        );

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $this->context->failed();
            $this->record($request, 500);
            $this->context->end();

            throw $e;
        }

        if ($response->getStatusCode() >= 400 || $this->isErrorResponse($response)) {
            $this->context->failed();
        }

        $this->record($request, $response->getStatusCode());
        $this->context->end();

        return $response;
    }

    protected function record(Request $request, int $httpStatus): void
    {
        $record = new CallRecord(
            callId: (string) $this->context->callId(),
            method: $this->context->method(),
            tool: $this->context->tool(),
            arguments: Sanitizer::arguments($this->context->arguments()),
            principal: $this->context->principal(),
            server: $this->context->server(),
            status: $this->context->status(),
            action: $this->context->action(),
            denial: $this->context->denial(),
            deniedAbility: $this->context->deniedAbility(),
            durationMs: $this->context->durationMs(),
            httpStatus: $httpStatus,
            requestId: $this->context->requestId(),
            client: $this->context->client(),
            ip: $request->ip(),
            userAgent: Str::limit((string) $request->userAgent(), 100),
            recordedActivityId: $this->context->recordedActivityId(),
        );

        $this->audit->recordCall($record);

        event(new ToolCallRecorded($record));
    }

    /**
     * @return array{method: string, tool: ?string, arguments: array<string, mixed>}|null
     */
    protected function parse(Request $request): ?array
    {
        $content = $request->getContent();

        if ($content === '' || $content === false) {
            return null;
        }

        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($data) || ! isset($data['method'])) {
            return null;
        }

        $params = (array) ($data['params'] ?? []);

        return [
            'method' => (string) $data['method'],
            'tool' => isset($params['name']) ? (string) $params['name'] : null,
            'arguments' => (array) ($params['arguments'] ?? []),
        ];
    }

    /**
     * A JSON-RPC error inside a 200 (a tool that threw) counts as failed.
     * Streamed responses are not inspected.
     */
    protected function isErrorResponse(Response $response): bool
    {
        if ($response instanceof StreamedResponse) {
            return false;
        }

        $content = (string) $response->getContent();

        return $content !== '' && str_contains($content, '"error"') && ! str_contains($content, '"result"');
    }
}
