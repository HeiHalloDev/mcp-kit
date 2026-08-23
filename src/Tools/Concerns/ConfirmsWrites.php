<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Tools\Concerns;

use HeiHallo\McpKit\Audit\McpCallContext;
use HeiHallo\McpKit\Audit\Sanitizer;
use HeiHallo\McpKit\Audit\WriteRecord;
use HeiHallo\McpKit\Contracts\AuditWriter;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Events\WriteConfirmed;
use HeiHallo\McpKit\Events\WritePreviewed;
use HeiHallo\McpKit\Principal;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use RuntimeException;
use Throwable;

/**
 * Preview-then-confirm for write tools, fused with the audit trail: a write
 * cannot be confirmed without being recorded or recorded without being
 * confirmed. One response shape everywhere:
 *
 *   preview:  {preview: true, action, ...preview, by, next}
 *   executed: {success: true, action, ...result}
 */
trait ConfirmsWrites
{
    public const NEXT_STEP = 'Nothing has changed. Show this to the person and call again with confirm=true.';

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

        $context = app(McpCallContext::class);

        if (! filter_var($request->get('confirm', false), FILTER_VALIDATE_BOOL)) {
            if ($context->isActive()) {
                $context->previewed($action);
            }

            event(new WritePreviewed($principal, $this->name(), $action, $preview));

            $payload = ['preview' => true, 'action' => $action, ...$preview];
            $payload['by'] = $principal->signature();
            $payload['next'] ??= self::NEXT_STEP;
            // Pre-1.0 alias of `next`; dropped in v1.0.
            $payload['note'] ??= $payload['next'];

            return Response::json($payload);
        }

        if (config('mcp-kit.read_only', false)) {
            return Response::error('The server is in read-only mode: nothing can be changed right now. Ask the person who runs it.');
        }

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

            if ($context->isActive()) {
                $context->failed();
            }

            return Response::error('The change failed unexpectedly and has been reported. Nothing may have been applied — read before retrying.');
        }

        if ($context->isActive()) {
            $context->executed($action);
        }

        $record = new WriteRecord(
            principal: $principal,
            tool: $this->name(),
            action: $action,
            arguments: Sanitizer::arguments($request->all()),
            details: Sanitizer::result($preview),
            result: Sanitizer::result($result),
            subject: $options['subject'] ?? null,
            reason: $options['reason'] ?? (is_string($request->get('reason')) ? $request->get('reason') : null),
            callId: $context->callId(),
        );

        app(AuditWriter::class)->recordWrite($record);

        event(new WriteConfirmed($record));

        return Response::json(['success' => true, 'action' => $action, ...$result]);
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
