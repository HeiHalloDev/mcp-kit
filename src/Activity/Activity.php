<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Activity;

use HeiHallo\McpKit\Enums\ActivityChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Optional base model with the kit's columns cast and a few scopes. The
 * provider points activitylog.activity_model here only when the app left
 * the plain Spatie model in place; apps with their own model keep it.
 */
class Activity extends SpatieActivity
{
    protected function casts(): array
    {
        return [...parent::casts(), 'channel' => ActivityChannel::class];
    }

    public function scopeMcp(Builder $query): Builder
    {
        return $query->where('log_name', config('mcp-kit.activity.log_name', 'mcp'));
    }

    public function scopeChannel(Builder $query, ActivityChannel|string $channel): Builder
    {
        return $query->where('channel', $channel instanceof ActivityChannel ? $channel->value : $channel);
    }

    public function scopeSource(Builder $query, string $source): Builder
    {
        return $query->where('source', $source);
    }

    public function scopeByCauser(Builder $query, Model $causer): Builder
    {
        return $query->where('causer_type', $causer->getMorphClass())->where('causer_id', $causer->getKey());
    }

    public function scopeForCall(Builder $query, string $callId): Builder
    {
        return $query->where('properties->call_id', $callId);
    }
}
