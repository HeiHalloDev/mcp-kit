<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Audit;

use HeiHallo\McpKit\Enums\ActivityChannel;
use HeiHallo\McpKit\Principal;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The call in flight. A scoped singleton: the middleware begins it, the
 * confirm helper and the ability checks mark it, the audit writer and the
 * activity stamper read from it. Inactive outside an HTTP tools/call.
 */
class McpCallContext
{
    public const STATUS_READ = 'read';

    public const STATUS_PREVIEWED = 'previewed';

    public const STATUS_EXECUTED = 'executed';

    public const STATUS_DENIED = 'denied';

    public const STATUS_FAILED = 'failed';

    protected bool $active = false;

    protected string $callId = '';

    protected string $method = '';

    protected ?string $tool = null;

    /** @var array<string, mixed> */
    protected array $arguments = [];

    protected ?Principal $principal = null;

    protected ?string $server = null;

    protected ?string $client = null;

    protected ?string $requestId = null;

    protected ActivityChannel $channel = ActivityChannel::Mcp;

    protected ?string $status = null;

    protected ?string $action = null;

    protected ?string $denial = null;

    protected ?string $deniedAbility = null;

    protected int|string|null $recordedActivityId = null;

    protected float $startedAt = 0.0;

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function begin(
        string $method,
        ?string $tool,
        array $arguments,
        ?Principal $principal,
        ?string $server,
        ?string $client,
        ?string $requestId,
        ActivityChannel $channel = ActivityChannel::Mcp,
    ): void {
        $this->active = true;
        $this->channel = $channel;
        $this->callId = (string) Str::uuid();
        $this->method = $method;
        $this->tool = $tool;
        $this->arguments = $arguments;
        $this->principal = $principal;
        $this->server = $server;
        $this->client = $client;
        $this->requestId = $requestId;
        $this->status = null;
        $this->action = null;
        $this->denial = null;
        $this->deniedAbility = null;
        $this->recordedActivityId = null;
        $this->startedAt = microtime(true);
    }

    public function end(): void
    {
        $this->active = false;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function callId(): ?string
    {
        return $this->active ? $this->callId : null;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function tool(): ?string
    {
        return $this->tool;
    }

    /**
     * @return array<string, mixed>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    public function principal(): ?Principal
    {
        return $this->principal;
    }

    public function server(): ?string
    {
        return $this->server;
    }

    public function client(): ?string
    {
        return $this->client;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * The surface the call came in on: mcp over HTTP, chat when an in-app
     * agent runs the tool on the web guard.
     */
    public function channel(): ActivityChannel
    {
        return $this->channel;
    }

    public function previewed(string $action): void
    {
        $this->status = self::STATUS_PREVIEWED;
        $this->action = $action;
    }

    public function executed(string $action): void
    {
        $this->status = self::STATUS_EXECUTED;
        $this->action = $action;
    }

    public function denied(string $ability, string $reason): void
    {
        $this->status = self::STATUS_DENIED;
        $this->denial = $reason;
        $this->deniedAbility = $ability;
    }

    public function failed(): void
    {
        $this->status = self::STATUS_FAILED;
    }

    public function status(): string
    {
        return $this->status ?? self::STATUS_READ;
    }

    public function action(): ?string
    {
        return $this->action;
    }

    public function denial(): ?string
    {
        return $this->denial;
    }

    public function deniedAbility(): ?string
    {
        return $this->deniedAbility;
    }

    public function recorded(int|string $activityId): void
    {
        $this->recordedActivityId = $activityId;
    }

    public function recordedActivityId(): int|string|null
    {
        return $this->recordedActivityId;
    }

    public function durationMs(): float
    {
        return $this->startedAt > 0 ? round((microtime(true) - $this->startedAt) * 1000, 2) : 0.0;
    }

    /**
     * The MCP client behind the call, from the user agent.
     */
    public static function clientFrom(Request $request): string
    {
        $agent = strtolower((string) $request->userAgent());

        return match (true) {
            str_contains($agent, 'claude-code'), str_contains($agent, 'claude code') => 'claude-code',
            str_contains($agent, 'claude') => 'claude',
            str_contains($agent, 'codex') => 'codex',
            str_contains($agent, 'cursor') => 'cursor',
            $agent === '' => 'unknown',
            default => 'other',
        };
    }
}
