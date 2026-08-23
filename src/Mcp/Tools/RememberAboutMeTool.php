<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Tools;

use HeiHallo\McpKit\Contracts\MemoryPolicy;
use HeiHallo\McpKit\Contracts\MemoryStore;
use HeiHallo\McpKit\Events\MemoryUpdated;
use HeiHallo\McpKit\Events\OnboardingCompleted;
use HeiHallo\McpKit\Events\OnboardingDeclined;
use HeiHallo\McpKit\Memory\AssistantMemory;
use HeiHallo\McpKit\Memory\MemoryLimits;
use HeiHallo\McpKit\Memory\MemoryMerger;
use HeiHallo\McpKit\Memory\SecretDetector;
use HeiHallo\McpKit\Principal;
use HeiHallo\McpKit\Tools\StaffTool;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class RememberAboutMeTool extends StaffTool
{
    protected string $name = 'remember_about_me';

    protected string $description = 'Save something about the person you are helping, for next time: their role, team, routines, who they hand things to, preferences, a note. Only what the person confirmed, only about the person — never about customers. Previews without confirm=true. Use forget to remove items; onboarding=completed|declined|reset marks the intro.';

    /**
     * @var array<string, mixed>
     */
    protected array $inputSchema = [
        'type' => 'object',
        'properties' => [
            'role' => ['type' => 'string', 'description' => 'What the person does, in their words.'],
            'team' => ['type' => 'string', 'description' => 'The team or area they belong to.'],
            'routines' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Things they usually do (one per item).'],
            'handoffs' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Who they hand what to.'],
            'preferences' => ['type' => 'object', 'additionalProperties' => true, 'description' => 'Key/value, e.g. {"language": "nb", "tone": "short"}. null removes a key.'],
            'note' => ['type' => 'string', 'description' => 'One free-form note to append.'],
            'forget' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Items to remove: a field name (role, routines, preferences.language), "note:2", the exact text of an item, or "everything".'],
            'onboarding' => ['type' => 'string', 'enum' => ['completed', 'declined', 'reset']],
            'replace' => ['type' => 'boolean', 'description' => 'Replace lists instead of merging into them.'],
            'user' => ['type' => 'string', 'description' => 'E-mail or id of another person (privileged only). Defaults to yourself.'],
            'confirm' => ['type' => 'boolean', 'description' => 'Preview without it; save with true.'],
        ],
    ];

    public function handle(Request $request): Response
    {
        $principal = $this->requirePerson($request);

        if ($principal instanceof Response) {
            return $principal;
        }

        $subject = $this->subject($request, $principal);

        if ($subject instanceof Response) {
            return $subject;
        }

        $changes = $request->all();
        unset($changes['confirm'], $changes['user'], $changes['replace']);

        if (SecretDetector::looksSecret($changes)) {
            return Response::error('That looks like a password or token. Memory is for how a person works, never for credentials.');
        }

        if ($changes === []) {
            return Response::error('Nothing to remember: pass at least one of role, team, routines, handoffs, preferences, note, forget or onboarding.');
        }

        $store = app(MemoryStore::class);
        $before = $store->get($subject);

        try {
            $after = (new MemoryMerger(MemoryLimits::fromConfig()))->merge(
                $before,
                $changes,
                filter_var($request->get('replace', false), FILTER_VALIDATE_BOOL),
                $principal,
            );
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        $diff = $this->diff($before, $after);
        $self = (string) $subject->getAuthIdentifier() === (string) $principal->id();

        return $this->previewOrExecute(
            $request,
            [
                'about' => $self ? 'you' : ($subject->name ?? $subject->getAuthIdentifier()),
                'changes' => $diff === [] ? 'nothing changes' : $diff,
                'size' => sprintf('%d of %d bytes', $after->sizeBytes(), MemoryLimits::fromConfig()->maxBytes),
            ],
            function () use ($store, $subject, $before, $after, $principal, $changes): array {
                $store->put($subject, $after);

                event(new MemoryUpdated($subject, $before, $after, $principal, 'mcp'));

                if (($changes['onboarding'] ?? null) === 'completed') {
                    event(new OnboardingCompleted($subject));
                } elseif (($changes['onboarding'] ?? null) === 'declined') {
                    event(new OnboardingDeclined($subject));
                }

                return ['memory' => $after->content()];
            },
            $self ? 'Remember about you' : 'Remember about '.($subject->name ?? 'user'),
            ['subject' => $subject],
        );
    }

    protected function subject(Request $request, Principal $principal): Authenticatable|Response
    {
        $target = $request->get('user');

        if ($target === null || $target === '' || (string) $target === (string) $principal->id()) {
            return $principal->tokenable;
        }

        $model = (string) config('auth.providers.users.model');
        $query = $model::query();
        $user = str_contains((string) $target, '@')
            ? $query->where('email', $target)->first()
            : $query->find($target);

        if ($user === null) {
            return Response::error("No user matches '{$target}'.");
        }

        if (! app(MemoryPolicy::class)->update($principal, $user)) {
            return Response::error('You may only change your own memory.');
        }

        return $user;
    }

    /**
     * @return array<string, array{from: mixed, to: mixed}>
     */
    protected function diff(AssistantMemory $before, AssistantMemory $after): array
    {
        $diff = [];

        foreach ($after->content() as $key => $value) {
            $old = $before->content()[$key] ?? null;

            if ($old !== $value) {
                $diff[$key] = ['from' => $old, 'to' => $value];
            }
        }

        return $diff;
    }
}
