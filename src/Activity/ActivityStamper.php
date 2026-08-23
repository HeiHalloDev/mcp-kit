<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Activity;

use HeiHallo\McpKit\Audit\McpCallContext;
use HeiHallo\McpKit\Contracts\ResolvesActivityChannel;
use HeiHallo\McpKit\Contracts\ResolvesActivitySource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Fills `channel`, `source`, `token_name` and properties.call_id on every
 * activity row as it is created, only where they are still null. Hooks
 * eloquent.creating on whatever activitylog.activity_model is, so apps keep
 * their own model and v4/v5 both work.
 */
class ActivityStamper
{
    /** @var array<string, bool> */
    protected static array $columns = [];

    public function __construct(
        protected ResolvesActivityChannel $channels,
        protected ResolvesActivitySource $sources,
        protected McpCallContext $context,
    ) {}

    public function __invoke(Model $activity): void
    {
        try {
            $this->stamp($activity);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function stamp(Model $activity): void
    {
        $principal = $this->context->isActive() ? $this->context->principal() : null;

        if ($this->hasColumn($activity, 'channel') && $activity->getAttribute('channel') === null) {
            $activity->setAttribute('channel', $this->channels->channel()->value);
        }

        if ($this->hasColumn($activity, 'source') && $activity->getAttribute('source') === null) {
            $source = $this->sources->source($principal, $activity);

            if ($source !== null) {
                $activity->setAttribute('source', $source);
            }
        }

        if ($this->hasColumn($activity, 'token_name') && $activity->getAttribute('token_name') === null && $principal?->tokenName() !== null) {
            $activity->setAttribute('token_name', $principal->tokenName());
        }

        if ($this->context->isActive()) {
            $properties = collect($activity->getAttribute('properties') ?? [])->toArray();

            if (! array_key_exists('call_id', $properties)) {
                $properties['call_id'] = $this->context->callId();
                $activity->setAttribute('properties', $properties);
            }
        }
    }

    protected function hasColumn(Model $activity, string $column): bool
    {
        return static::columnExists($activity, $column);
    }

    /**
     * Cached per process: one schema lookup per column, not one per row.
     */
    public static function columnExists(Model $activity, string $column): bool
    {
        $key = $activity->getConnectionName().'.'.$activity->getTable().'.'.$column;

        return static::$columns[$key] ??= Schema::connection($activity->getConnectionName())->hasColumn($activity->getTable(), $column);
    }

    public static function forgetColumnCache(): void
    {
        static::$columns = [];
    }
}
