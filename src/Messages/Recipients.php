<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Messages;

use HeiHallo\McpKit\Contracts\PermissionChecker;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Who a message can reach: the people who use the tools in this app, since
 * a message only arrives on top of a tool result. Searching the whole users
 * table found customers and students who never would, and missed the
 * developer, whose account may be called "HeiHallo" while everyone writes
 * "Anders".
 *
 * The developer is found by role (`people.builder_roles`, Dev by default)
 * or by `people.builder`, a name, aliases and e-mails for the person behind
 * the Dev account, so "dev", "Anders" or either address all reach them.
 */
class Recipients
{
    /** Words that mean the people who build and run this app. */
    public const BUILDER_WORDS = ['dev', 'developer', 'the developer', 'utvikler', 'utvikleren', 'admin', 'serveransvarlig'];

    /**
     * People who use the tools here: anyone holding a token or a sign-in.
     *
     * @return Builder<Model>|null
     */
    public function toolUsers(Model $model): ?Builder
    {
        if (! method_exists($model, 'tokens')) {
            return null;
        }

        return $model->newQuery()->whereHas('tokens');
    }

    /**
     * The people who build and run this app, as tool users.
     *
     * @return Collection<int, Model>
     */
    public function builders(Model $model): Collection
    {
        $users = $this->toolUsers($model);

        if ($users === null) {
            return new Collection;
        }

        $emails = array_map('mb_strtolower', array_filter((array) config('mcp-kit.people.builder.emails', [])));
        $roles = array_filter((array) config('mcp-kit.people.builder_roles', ['Dev']));

        $found = new Collection;

        if ($emails !== []) {
            $found = $found->merge((clone $users)->whereIn(DB::raw('lower(email)'), $emails)->get());
        }

        if ($roles !== [] && method_exists($model, 'roles')) {
            $found = $found->merge((clone $users)->whereHas('roles', fn (Builder $query) => $query->whereIn('name', $roles))->get());
        }

        // An app without roles or configured addresses: its privileged people.
        if ($found->isEmpty() && $emails === [] && ! method_exists($model, 'roles')) {
            $checker = app(PermissionChecker::class);
            $found = (clone $users)->get()->filter(fn (Model $user): bool => $user instanceof Authenticatable && $checker->isPrivileged($user));
        }

        return $found->unique(fn (Model $user) => $user->getKey())->values();
    }

    /**
     * "Built and run by: Anders Heim" — the configured name, or the Dev
     * account's own.
     */
    public function builderName(?Model $model): ?string
    {
        $configured = trim((string) config('mcp-kit.people.builder.name', ''));

        if ($configured !== '') {
            return $configured;
        }

        if ($model === null) {
            return null;
        }

        $names = $this->builders($model)->map(fn (Model $user): string => (string) $user->getAttribute('name'))->filter()->unique();

        return $names->isEmpty() ? null : $names->implode(', ');
    }

    /**
     * builderName for a page that must render whatever happens: whoami and
     * the ground rules.
     */
    public function builderNameOrNull(mixed $model): ?string
    {
        try {
            return $this->builderName($model instanceof Model ? $model : null);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * The people behind one name, first name, part of a name or e-mail.
     *
     * @return Collection<int, Model>
     */
    public function match(Model $model, string $who): Collection
    {
        $users = $this->toolUsers($model);
        $who = trim($who);
        $lower = mb_strtolower($who);

        if ($users === null || $who === '') {
            return new Collection;
        }

        if ($this->isBuilderWord($who)) {
            return $this->builders($model);
        }

        $exact = (clone $users)
            ->where(fn (Builder $query) => $query->whereRaw('lower(email) = ?', [$lower])->orWhereRaw('lower(name) = ?', [$lower]))
            ->limit(5)
            ->get();

        if ($exact->isNotEmpty() || str_contains($who, '@')) {
            return $exact;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $lower).'%';

        return (clone $users)->whereRaw('lower(name) like ?', [$like])->limit(5)->get();
    }

    /**
     * Anyone with an account here, for filing a gap on someone's behalf:
     * they need not hold a connection, since the answer waits for them.
     *
     * @return Collection<int, Model>
     */
    public function matchAnyone(Model $model, string $who): Collection
    {
        $who = trim($who);
        $lower = mb_strtolower($who);

        if ($who === '') {
            return new Collection;
        }

        $exact = $model->newQuery()
            ->where(fn (Builder $query) => $query->whereRaw('lower(email) = ?', [$lower])->orWhereRaw('lower(name) = ?', [$lower]))
            ->limit(5)
            ->get();

        if ($exact->isNotEmpty() || str_contains($who, '@')) {
            return $exact;
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $lower).'%';

        return $model->newQuery()->whereRaw('lower(name) like ?', [$like])->limit(5)->get();
    }

    /**
     * Some names of people who can be reached, for a reply that found nobody.
     *
     * @return list<string>
     */
    public function someNames(Model $model, int $limit = 12): array
    {
        $users = $this->toolUsers($model);

        return $users === null ? [] : $users->orderBy('name')->limit($limit)->pluck('name')->map(fn ($name) => (string) $name)->filter()->values()->all();
    }

    /**
     * Whether `to` names the developer: "dev", or the configured builder's
     * name, an alias or an address.
     */
    public function isBuilderWord(string $who): bool
    {
        return in_array(mb_strtolower(trim($who)), $this->builderWords(), true);
    }

    /**
     * @return list<string>
     */
    private function builderWords(): array
    {
        $builder = (array) config('mcp-kit.people.builder', []);

        return array_values(array_unique(array_filter(array_map(
            fn ($word) => mb_strtolower(trim((string) $word)),
            [...self::BUILDER_WORDS, $builder['name'] ?? '', ...(array) ($builder['aliases'] ?? []), ...(array) ($builder['emails'] ?? [])],
        ))));
    }
}
