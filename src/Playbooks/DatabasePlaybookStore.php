<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Playbooks;

use HeiHallo\McpKit\Contracts\PlaybookStore;
use HeiHallo\McpKit\Models\Playbook as PlaybookModel;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Playbooks in the mcp_playbooks table: the person's own, plus the ones
 * colleagues marked as shared.
 */
class DatabasePlaybookStore implements PlaybookStore
{
    /**
     * @var array<string, ?string>
     */
    private array $authors = [];

    public function visibleTo(Authenticatable $user): array
    {
        $rows = $this->model()->newQuery()
            ->visibleTo($this->key($user))
            ->orderBy('name')
            ->get();

        return $rows->map(fn (PlaybookModel $row): Playbook => $this->toPlaybook($row))->all();
    }

    public function ownedBy(Authenticatable $user): array
    {
        $rows = $this->model()->newQuery()
            ->where('user_id', $this->key($user))
            ->orderBy('name')
            ->get();

        return $rows->map(fn (PlaybookModel $row): Playbook => $this->toPlaybook($row))->all();
    }

    public function find(Authenticatable $user, string $name): ?Playbook
    {
        $row = $this->model()->newQuery()
            ->visibleTo($this->key($user))
            ->where('name', $name)
            ->first();

        return $row === null ? null : $this->toPlaybook($row);
    }

    public function put(Authenticatable $user, Playbook $playbook): Playbook
    {
        $row = $this->model()->newQuery()->updateOrCreate(
            ['user_id' => $this->key($user), 'name' => $playbook->name],
            [
                'title' => $playbook->title,
                'description' => $playbook->description,
                'body' => $playbook->body,
                'arguments' => $playbook->arguments,
                'servers' => $playbook->servers,
                'abilities' => $playbook->abilities,
                'shared' => $playbook->shared,
            ],
        );

        return $this->toPlaybook($row);
    }

    public function forget(Authenticatable $user, string $name): bool
    {
        return $this->model()->newQuery()
            ->where('user_id', $this->key($user))
            ->where('name', $name)
            ->delete() > 0;
    }

    public function recordUse(Authenticatable $user, string $name): void
    {
        $this->model()->newQuery()
            ->visibleTo($this->key($user))
            ->where('name', $name)
            ->each(function (PlaybookModel $row): void {
                $row->forceFill([
                    'uses' => $row->uses + 1,
                    'last_used_at' => now(),
                ])->saveQuietly();
            });
    }

    /**
     * How many this person has already saved, for the cap.
     */
    public function countFor(Authenticatable $user): int
    {
        return $this->model()->newQuery()->where('user_id', $this->key($user))->count();
    }

    protected function toPlaybook(PlaybookModel $row): Playbook
    {
        return new Playbook(
            name: (string) $row->name,
            title: (string) $row->title,
            description: (string) $row->description,
            body: (string) $row->body,
            arguments: $this->arguments($row),
            servers: array_values(array_map('strval', $row->servers ?? [])),
            abilities: array_values(array_map('strval', $row->abilities ?? [])),
            shared: (bool) $row->shared,
            userId: $row->user_id,
            author: $this->author($row),
            uses: (int) $row->uses,
            lastUsedAt: $row->last_used_at,
        );
    }

    /**
     * @return list<array{name: string, description: string, required: bool}>
     */
    protected function arguments(PlaybookModel $row): array
    {
        $arguments = [];

        foreach ($row->arguments ?? [] as $argument) {
            if (! is_array($argument) || ! isset($argument['name'])) {
                continue;
            }

            $arguments[] = [
                'name' => (string) $argument['name'],
                'description' => (string) ($argument['description'] ?? ''),
                'required' => (bool) ($argument['required'] ?? false),
            ];
        }

        return $arguments;
    }

    /**
     * The author's name, for shared playbooks. Looked up once per request.
     */
    protected function author(PlaybookModel $row): ?string
    {
        if (! $row->shared) {
            return null;
        }

        $id = (string) $row->user_id;

        if (! array_key_exists($id, $this->authors)) {
            $model = config('auth.providers.users.model');
            $user = is_string($model) && class_exists($model) ? $model::query()->find($id) : null;

            $this->authors[$id] = $user?->name === null ? null : (string) $user->name;
        }

        return $this->authors[$id];
    }

    protected function model(): PlaybookModel
    {
        $class = (string) config('mcp-kit.playbooks.model', PlaybookModel::class);

        return new $class;
    }

    protected function key(Authenticatable $user): string
    {
        return (string) $user->getAuthIdentifier();
    }
}
