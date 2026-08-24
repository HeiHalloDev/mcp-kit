<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tools\Concerns;

use HeiHallo\McpKit\Audit\McpCallContext;
use HeiHallo\McpKit\Audit\Sanitizer;
use HeiHallo\McpKit\Audit\WriteRecord;
use HeiHallo\McpKit\Contracts\AuditWriter;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Enums\ActivityChannel;
use HeiHallo\McpKit\Events\WriteConfirmed;
use HeiHallo\McpKit\Events\WritePreviewed;
use HeiHallo\McpKit\Principal;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;
use Throwable;

/**
 * Preview-then-confirm for write tools, fused with the audit trail: a write
 * cannot be confirmed without being recorded or recorded without being
 * confirmed. One response shape everywhere:
 *
 *   preview:  {preview: true, action, ...preview, by, next}
 *   executed: {success: true, action, ...result}
 *
 * Every confirmed execution runs inside a call context — the one the HTTP
 * middleware opened, or one opened here for in-app agents and tests — so
 * the domain rows written meanwhile share the call id and channel.
 */
trait ConfirmsWrites
{
    public const NEXT_STEP = 'Nothing has changed. Show this to the person and call again with confirm=true.';

    private bool $writeRecorded = false;

    /**
     * Without `confirm=true`: return the preview and do nothing. With it:
     * run `$execute`, record the write in the caller's name, return its result.
     *
     * @param  array<string, mixed>  $preview
     * @param  callable(): array<string, mixed>  $execute
     * @param  array{subject?: ?object, reason?: ?string, exception_messages?: array<class-string, string|callable>}  $options
     */
    protected function previewOrExecute(Request $request, array $preview, callable $execute, string $action, array $options = []): Response
    {
        $principal = $this->writingPrincipal($request);

        if ($principal instanceof Response) {
            return $principal;
        }

        if (! filter_var($request->get('confirm', false), FILTER_VALIDATE_BOOL)) {
            $context = app(McpCallContext::class);

            if ($context->isActive()) {
                $context->previewed($action);
            }

            event(new WritePreviewed($principal, $this->name(), $action, $preview));

            $payload = ['preview' => true, 'action' => $action, ...$preview];
            $payload['by'] = $principal->signature();
            $payload['next'] ??= self::NEXT_STEP;

            return Response::json($payload);
        }

        if (config('mcp-kit.read_only', false)) {
            return Response::error('The server is in read-only mode: nothing can be changed right now. Ask the person who runs it.');
        }

        return $this->withinCall($request, function () use ($request, $preview, $execute, $action, $options): Response {
            try {
                $result = $execute();
            } catch (Throwable $e) {
                foreach ((array) ($options['exception_messages'] ?? []) as $class => $message) {
                    if ($e instanceof $class) {
                        return Response::error(is_callable($message) ? (string) $message($e) : (string) $message);
                    }
                }

                if ($e instanceof RuntimeException) {
                    return Response::error($e->getMessage());
                }

                report($e);

                $context = app(McpCallContext::class);

                if ($context->isActive()) {
                    $context->failed();
                }

                return Response::error('The change failed unexpectedly and has been reported. Nothing may have been applied — read before retrying.');
            }

            $this->recordWrite(
                $request,
                $action,
                $preview,
                $result,
                $options['subject'] ?? null,
                $options['reason'] ?? (is_string($request->get('reason')) ? $request->get('reason') : null),
            );

            return Response::json(['success' => true, 'action' => $action, ...$result]);
        });
    }

    /**
     * One activity row for a confirmed write, in the caller's name. Called
     * by previewOrExecute(); a base class that makes the audit
     * unconditional calls it for writes that bypass the helper.
     *
     * @param  array<string, mixed>  $details
     * @param  array<string, mixed>  $result
     */
    protected function recordWrite(Request $request, string $action, array $details = [], array $result = [], ?object $subject = null, ?string $reason = null): void
    {
        if ($this->writeRecorded) {
            return;
        }

        $principal = $this->writingPrincipal($request);

        if ($principal instanceof Response) {
            return;
        }

        $context = app(McpCallContext::class);

        if ($context->isActive()) {
            $context->executed($action);
        }

        $record = new WriteRecord(
            principal: $principal,
            tool: $this->name(),
            action: $action,
            arguments: Sanitizer::arguments($request->all()),
            details: Sanitizer::result($details),
            result: Sanitizer::result($result),
            subject: $subject,
            reason: $reason,
            callId: $context->callId(),
        );

        app(AuditWriter::class)->recordWrite($record);

        event(new WriteConfirmed($record));

        $this->writeRecorded = true;
    }

    protected function writeWasRecorded(): bool
    {
        return $this->writeRecorded;
    }

    /**
     * Run a callback inside a call context. Over HTTP the middleware has
     * already opened one; otherwise (an in-app agent on the web guard, a
     * direct invocation, a test) one is opened here and closed after, with
     * the channel set to chat when the caller has no personal token.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function withinCall(Request $request, callable $callback): mixed
    {
        $context = app(McpCallContext::class);

        if ($context->isActive()) {
            return $callback();
        }

        $principal = $this->writingPrincipal($request);
        $principal = $principal instanceof Principal ? $principal : null;

        $context->begin(
            method: 'tools/call',
            tool: $this->name(),
            arguments: $request->all(),
            principal: $principal,
            server: null,
            client: 'direct',
            requestId: null,
            channel: $principal?->token instanceof PersonalAccessToken ? ActivityChannel::Mcp : ActivityChannel::Chat,
        );

        try {
            return $callback();
        } finally {
            $context->end();
        }
    }

    /**
     * Writes that matter need a reason written down.
     */
    protected function requireReason(Request $request, int $minLength = 5): ?Response
    {
        $reason = trim((string) $request->get('reason', ''));

        if (mb_strlen($reason) < $minLength) {
            return Response::error("A reason of at least {$minLength} characters is required — it goes on the activity log.");
        }

        return null;
    }

    private function writingPrincipal(Request $request): Principal|Response
    {
        $tokenable = $request->user();

        if ($tokenable === null) {
            return Response::error('Authentication required.');
        }

        $principal = app(PrincipalResolver::class)->resolve($tokenable);

        if ($principal === null) {
            return Response::error('This token is not permitted to use the MCP server.');
        }

        return $principal;
    }
}
