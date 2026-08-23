<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Audit;

use HeiHallo\McpKit\Activity\ActivityStamper;
use HeiHallo\McpKit\Contracts\AuditWriter;
use HeiHallo\McpKit\Enums\ActivityChannel;
use HeiHallo\McpKit\Principal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Activitylog\Contracts\Activity;
use Throwable;

/**
 * Every call becomes an activity_log row in the `mcp` log (one per
 * tools/call), and one line in the `mcp` log channel when it exists. Rows
 * written during a call share properties.call_id. Never throws: a broken
 * audit must not break the call, so failures are reported instead.
 */
class ActivityLogAuditWriter implements AuditWriter
{
    public function recordCall(CallRecord $record): void
    {
        $this->log($record->httpStatus >= 400 || $record->status === McpCallContext::STATUS_FAILED ? 'warning' : 'info', 'MCP tool call', [
            'method' => $record->method,
            'tool' => $record->tool,
            'server' => $record->server,
            'status' => $record->status,
            'action' => $record->action,
            'arguments' => $record->arguments,
            'by' => $record->principal?->signature(),
            'principal_type' => $record->principal?->tokenable::class,
            'principal_id' => $record->principal?->id(),
            'token_name' => $record->principal?->tokenName(),
            'client' => $record->client,
            'ip' => $record->ip,
            'user_agent' => $record->userAgent,
            'duration_ms' => $record->durationMs,
            'http_status' => $record->httpStatus,
            'request_id' => $record->requestId,
            'call_id' => $record->callId,
        ]);

        try {
            // The confirm helper already wrote the executed row: finish it.
            if ($record->recordedActivityId !== null) {
                $this->finishRow($record);

                return;
            }

            $activity = activity($this->logName())
                ->event($record->status)
                ->withProperties([
                    'tool' => $record->tool,
                    'server' => $record->server,
                    'method' => $record->method,
                    'arguments' => $record->arguments,
                    'status' => $record->status,
                    'reason' => $record->denial,
                    'ability' => $record->deniedAbility,
                    'duration_ms' => $record->durationMs,
                    'call_id' => $record->callId,
                    'request_id' => $record->requestId,
                    'client' => $record->client,
                    'token_id' => $record->principal?->token?->id,
                    ...$this->principalProperties($record->principal),
                ]);

            $this->attachCauser($activity, $record->principal);

            $activity->log($record->action ?? $record->tool ?? $record->method);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function recordWrite(WriteRecord $record): void
    {
        try {
            $activity = activity($this->logName())
                ->event(McpCallContext::STATUS_EXECUTED)
                ->withProperties([
                    'tool' => $record->tool,
                    'server' => app(McpCallContext::class)->server(),
                    'method' => 'tools/call',
                    'arguments' => $record->arguments,
                    'details' => $record->details,
                    'result' => $record->result,
                    'reason' => $record->reason,
                    'status' => McpCallContext::STATUS_EXECUTED,
                    'call_id' => $record->callId,
                    'client' => app(McpCallContext::class)->client(),
                    'token_id' => $record->principal->token?->id,
                    ...$this->principalProperties($record->principal),
                ]);

            $this->attachCauser($activity, $record->principal);

            if ($record->subject instanceof Model) {
                $activity->performedOn($record->subject);
            }

            $row = $activity->log($record->action);

            if ($row instanceof Activity && app(McpCallContext::class)->isActive()) {
                app(McpCallContext::class)->recorded($row->getKey());
            }
        } catch (Throwable $e) {
            report($e);
        }

        $this->log('info', 'MCP write executed', [
            'tool' => $record->tool,
            'action' => $record->action,
            'by' => $record->principal->signature(),
            'call_id' => $record->callId,
        ]);
    }

    public function recordToken(string $event, PersonalAccessToken $token, ?Principal $by, array $details = []): void
    {
        $this->log('info', "MCP token {$event}", [
            'token_id' => $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities,
            'owner_type' => $token->tokenable_type,
            'owner_id' => $token->tokenable_id,
            'by' => $by?->signature(),
            ...$details,
        ]);

        try {
            $activity = activity($this->logName())
                ->event("token_{$event}")
                ->withProperties([
                    'token_id' => $token->id,
                    'token_name' => $token->name,
                    'abilities' => $token->abilities,
                    'expires_at' => $token->expires_at?->toIso8601String(),
                    'owner_type' => $token->tokenable_type,
                    'owner_id' => $token->tokenable_id,
                    ...$details,
                ]);

            $this->attachCauser($activity, $by);

            $owner = $token->tokenable;

            if ($owner instanceof Model) {
                $activity->performedOn($owner);
            }

            $activity->log("Token {$event}: {$token->name}");
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function recordDenial(?Principal $principal, string $reason, array $details = []): void
    {
        $this->log('warning', 'MCP access denied', [
            'reason' => $reason,
            'by' => $principal?->signature(),
            'principal_type' => $principal?->tokenable::class,
            'principal_id' => $principal?->id(),
            ...$details,
        ]);
    }

    protected function logName(): string
    {
        return (string) config('mcp-kit.activity.log_name', 'mcp');
    }

    /**
     * @return array<string, mixed>
     */
    protected function principalProperties(?Principal $principal): array
    {
        if ($principal === null) {
            return [];
        }

        if ($principal->isService()) {
            return ['client_name' => $principal->name, 'service_client_id' => $principal->id()];
        }

        return [];
    }

    /**
     * The causer, and the kit's own columns set outright: an audit row is
     * always channel=mcp, whatever the surrounding request looks like.
     * Untyped on purpose: v4 has Spatie\Activitylog\ActivityLogger, v5 moved
     * it to Spatie\Activitylog\Support\ActivityLogger.
     *
     * @param  object  $activity  the logger returned by activity()
     */
    protected function attachCauser(object $activity, ?Principal $principal): void
    {
        if ($principal?->tokenable instanceof Model) {
            $activity->causedBy($principal->tokenable);
        }

        $activity->tap(function (Model $row) use ($principal): void {
            if (ActivityStamper::columnExists($row, 'channel')) {
                $row->setAttribute('channel', ActivityChannel::Mcp->value);
            }

            if ($principal?->tokenName() !== null && ActivityStamper::columnExists($row, 'token_name')) {
                $row->setAttribute('token_name', $principal->tokenName());
            }
        });
    }

    protected function finishRow(CallRecord $record): void
    {
        $model = config('activitylog.activity_model', \Spatie\Activitylog\Models\Activity::class);
        $row = $model::query()->find($record->recordedActivityId);

        if ($row === null) {
            return;
        }

        $properties = collect($row->properties ?? [])->toArray();
        $properties['duration_ms'] = $record->durationMs;
        $properties['request_id'] = $record->requestId;
        $properties['client'] ??= $record->client;
        $properties['server'] ??= $record->server;

        $row->properties = $properties;
        $row->save();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function log(string $level, string $message, array $context): void
    {
        try {
            $channel = array_key_exists('mcp', (array) config('logging.channels', [])) ? Log::channel('mcp') : Log::getFacadeRoot();
            $channel->{$level}($message, $context);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
