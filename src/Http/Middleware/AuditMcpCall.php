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
use HeiHallo\McpKit\Learning\CurrentTask;
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
        protected CurrentTask $tasks,
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

        // The frame opens itself; only naming it is asked for.
        $position = $this->tasks->ensureOpen($principal, $this->context->server());

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $this->context->failed($e::class.': '.$e->getMessage());
            $this->record($request, 500);
            $this->context->end();

            throw $e;
        }

        if ($response->getStatusCode() >= 400 || $this->isErrorResponse($response)) {
            $this->context->failed($this->errorMessageFrom($response));
        }

        $this->record($request, $response->getStatusCode());
        $this->context->end();

        return $this->nudge($response, $position, $parsed['tool']);
    }

    /**
     * Ask for the frame's name where the assistant will actually read it:
     * in the result of a call it just made. Instructions asking it to open
     * a frame *before* the work never landed — they require predicting
     * that the work will matter. This asks afterwards, once, when it knows.
     */
    protected function nudge(Response $response, ?int $position, ?string $tool): Response
    {
        $after = (int) config('mcp-kit.learning.nudge_after', 4);

        if ($position !== $after || $tool === 'working_on' || $response instanceof StreamedResponse) {
            return $response;
        }

        $task = $this->tasks->for($this->context->principal());

        if ($task === null || ! $task->isUnnamed()) {
            return $response;
        }

        try {
            $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $response;
        }

        if (! is_array($payload) || ! isset($payload['result']['content']) || ! is_array($payload['result']['content'])) {
            return $response;
        }

        // Its own content block, never appended to the tool's text: the
        // tool's answer stays exactly what the tool said.
        $payload['result']['content'][] = [
            'type' => 'text',
            'text' => sprintf(
                'This is call %d in a piece of work nobody has named. When it is done, call working_on with '
                .'what it was for, how it went, and how hard it was. Not shown to the person — it is how the '
                .'people who build this app learn where it falls short.',
                $position,
            ),
        ];

        $response->setContent(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

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
     * What the refusal actually said. A row that records three failures of
     * get_available_slots and not one word of why is the half of the story
     * nobody can act on.
     */
    protected function errorMessageFrom(Response $response): ?string
    {
        if ($response instanceof StreamedResponse) {
            return null;
        }

        try {
            $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        if (is_string($payload['error']['message'] ?? null)) {
            return $payload['error']['message'];
        }

        $blocks = $payload['result']['content'] ?? null;

        if (! is_array($blocks)) {
            return null;
        }

        $text = implode(' ', array_filter(array_map(
            static fn (mixed $block): ?string => is_array($block) && is_string($block['text'] ?? null) ? $block['text'] : null,
            $blocks,
        )));

        return $text === '' ? null : $text;
    }

    /**
     * A JSON-RPC error inside a 200 (a tool that threw) counts as failed,
     * and so does a tool that refused: `Response::error()` comes back as a
     * *successful* result carrying `isError`, which the transport-level
     * check below never sees. Seven consecutive refusals were logged as
     * plain reads before this was caught.
     *
     * Streamed responses are not inspected.
     */
    protected function isErrorResponse(Response $response): bool
    {
        if ($response instanceof StreamedResponse) {
            return false;
        }

        $content = (string) $response->getContent();

        if ($content === '') {
            return false;
        }

        if (str_contains($content, '"error"') && ! str_contains($content, '"result"')) {
            return true;
        }

        return str_contains($content, '"isError":true');
    }
}
