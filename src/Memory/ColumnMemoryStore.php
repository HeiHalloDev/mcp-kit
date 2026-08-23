<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Memory;

use HeiHallo\McpKit\Contracts\MemoryStore;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

/**
 * One JSON column on the users table (mcp-kit.memory.table / column). Reads
 * and writes go through the query builder so the users model needs no cast
 * and no fillable entry.
 */
class ColumnMemoryStore implements MemoryStore
{
    public function __construct(protected ConnectionResolverInterface $db) {}

    public function get(Authenticatable $user): AssistantMemory
    {
        $raw = $this->query($user)->value($this->column());

        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        return AssistantMemory::fromArray(is_array($raw) ? $raw : null);
    }

    public function put(Authenticatable $user, AssistantMemory $memory): void
    {
        $this->query($user)->update([
            $this->column() => $memory->isBlank() ? null : json_encode($memory->toArray(), JSON_UNESCAPED_UNICODE),
        ]);

        if ($user instanceof Model) {
            $user->setAttribute($this->column(), $memory->isBlank() ? null : $memory->toArray());
            $user->syncOriginalAttribute($this->column());
        }
    }

    protected function column(): string
    {
        return (string) config('mcp-kit.memory.column', 'assistant_memory');
    }

    protected function query(Authenticatable $user): Builder
    {
        $table = config('mcp-kit.memory.table');
        $connection = null;

        if ($user instanceof Model) {
            $table = is_string($table) && $table !== '' ? $table : $user->getTable();
            $connection = $user->getConnectionName();
            $key = $user->getKeyName();
        } else {
            $table = is_string($table) && $table !== '' ? $table : 'users';
            $key = $user->getAuthIdentifierName();
        }

        return $this->db->connection($connection)->table($table)->where($key, $user->getAuthIdentifier());
    }
}
