<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Tools;

use HeiHallo\McpKit\Contracts\PlaybookPolicy;
use HeiHallo\McpKit\Contracts\PlaybookStore;
use HeiHallo\McpKit\Events\PlaybookForgotten;
use HeiHallo\McpKit\Events\PlaybookSaved;
use HeiHallo\McpKit\Memory\SecretDetector;
use HeiHallo\McpKit\Playbooks\DatabasePlaybookStore;
use HeiHallo\McpKit\Playbooks\Playbook;
use HeiHallo\McpKit\Playbooks\PlaybookFactory;
use HeiHallo\McpKit\Playbooks\PlaybookLimits;
use HeiHallo\McpKit\Principal;
use HeiHallo\McpKit\Tools\StaffTool;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
class SavePlaybookTool extends StaffTool
{
    protected string $name = 'save_playbook';

    protected string $description = 'Save a way of working the person wants back next time: the steps, in the order they are done. It becomes a prompt their client can run by name. Previews without confirm=true. Pass delete=true to remove one. Only save what the person asked you to save.';

    /**
     * @var array<string, mixed>
     */
    protected array $inputSchema = [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string', 'description' => 'Short name, the one the person will type: weekly_digest, chase_unpaid.'],
            'title' => ['type' => 'string', 'description' => 'Human title shown in the client. Defaults to the name.'],
            'description' => ['type' => 'string', 'description' => 'One line: what it does and when to reach for it.'],
            'body' => ['type' => 'string', 'description' => 'The steps, in order, in the words the person used. Use {{ placeholder }} for anything filled in at run time, and declare each one in arguments.'],
            'arguments' => [
                'type' => 'array',
                'description' => 'The placeholders the body uses.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'required' => ['type' => 'boolean'],
                    ],
                    'required' => ['name'],
                ],
            ],
            'servers' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Server keys this belongs to. Leave out to offer it everywhere.'],
            'abilities' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Abilities it needs; it stays hidden from tokens that lack them.'],
            'shared' => ['type' => 'boolean', 'description' => 'Offer it to every colleague, not just this person. Privileged only.'],
            'delete' => ['type' => 'boolean', 'description' => 'Remove the named playbook instead of saving it.'],
            'confirm' => ['type' => 'boolean', 'description' => 'Preview without it; save with true.'],
        ],
        'required' => ['name'],
    ];

    public function handle(Request $request): Response
    {
        $principal = $this->requirePerson($request);

        if ($principal instanceof Response) {
            return $principal;
        }

        if (! config('mcp-kit.playbooks.enabled', true)) {
            return Response::error('Playbooks are turned off in this app.');
        }

        $policy = app(PlaybookPolicy::class);

        if (! $policy->save($principal)) {
            return Response::error('You may not save playbooks here.');
        }

        $user = $principal->tokenable;
        $store = app(PlaybookStore::class);

        if (filter_var($request->get('delete', false), FILTER_VALIDATE_BOOL)) {
            return $this->delete($request, $principal, $user, $store);
        }

        return $this->save($request, $principal, $user, $store, $policy);
    }

    protected function save(Request $request, Principal $principal, Authenticatable $user, PlaybookStore $store, PlaybookPolicy $policy): Response
    {
        $input = $request->all();
        unset($input['confirm'], $input['delete']);

        if (SecretDetector::looksSecret($input)) {
            return Response::error('That looks like a password or token. A playbook says how the work is done; credentials never belong in one.');
        }

        try {
            $name = Playbook::slug((string) $request->get('name', ''));
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        $existing = $store->find($user, $name);

        if ($existing !== null && ! $policy->edit($principal, $existing)) {
            return Response::error(sprintf(
                "'%s' was shared by %s. Save yours under a different name rather than changing theirs.",
                $name,
                $existing->author ?? 'a colleague',
            ));
        }

        try {
            $playbook = app(PlaybookFactory::class)->make($input, $existing);
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        if ($playbook->shared && ! $policy->share($principal)) {
            return Response::error('Sharing a playbook with everyone is for privileged staff. Saved for you alone instead — call again without shared.');
        }

        if ($existing === null && ($full = $this->atCapacity($store, $user)) !== null) {
            return $full;
        }

        return $this->previewOrExecute(
            $request,
            [
                'playbook' => $playbook->name,
                'change' => $existing === null ? 'new' : 'replaces the one you saved',
                'runs_as' => $this->promptName($playbook),
                'title' => $playbook->title,
                'description' => $playbook->description,
                'steps' => $playbook->body,
                'arguments' => $playbook->arguments === [] ? 'none' : array_column($playbook->arguments, 'name'),
                'servers' => $playbook->servers === [] ? 'every server' : $playbook->servers,
                'needs' => $playbook->abilities === [] ? 'nothing in particular' : $playbook->abilities,
                'visible_to' => $playbook->shared ? 'everyone here' : 'you',
            ],
            function () use ($store, $user, $playbook, $principal): array {
                $saved = $store->put($user, $playbook);

                event(new PlaybookSaved($user, $saved, $principal));

                return ['playbook' => $saved->toArray()];
            },
            $existing === null ? 'Save playbook' : 'Update playbook',
        );
    }

    protected function delete(Request $request, Principal $principal, Authenticatable $user, PlaybookStore $store): Response
    {
        try {
            $name = Playbook::slug((string) $request->get('name', ''));
        } catch (InvalidArgumentException $e) {
            return Response::error($e->getMessage());
        }

        $playbook = $store->find($user, $name);

        if ($playbook === null) {
            return Response::error("You have no playbook called '{$name}'.");
        }

        if (! app(PlaybookPolicy::class)->edit($principal, $playbook)) {
            return Response::error(sprintf(
                "'%s' belongs to %s, so it is not yours to remove.",
                $name,
                $playbook->author ?? 'a colleague',
            ));
        }

        return $this->previewOrExecute(
            $request,
            [
                'playbook' => $playbook->name,
                'title' => $playbook->title,
                'used' => $playbook->uses === 0 ? 'never' : sprintf('%d times', $playbook->uses),
                'steps' => $playbook->body,
            ],
            function () use ($store, $user, $playbook, $principal): array {
                $store->forget($user, $playbook->name);

                event(new PlaybookForgotten($user, $playbook, $principal));

                return ['forgot' => $playbook->name];
            },
            'Forget playbook',
        );
    }

    /**
     * The cap is per person, and only the default store can count.
     */
    protected function atCapacity(PlaybookStore $store, Authenticatable $user): ?Response
    {
        $limits = PlaybookLimits::fromConfig();
        $count = $store instanceof DatabasePlaybookStore
            ? $store->countFor($user)
            : count($store->ownedBy($user));

        if ($count < $limits->perPerson) {
            return null;
        }

        return Response::error(sprintf(
            'You already have %d playbooks, which is the limit. Delete one first: save_playbook with delete=true.',
            $limits->perPerson,
        ));
    }

    protected function promptName(Playbook $playbook): string
    {
        return ((string) config('mcp-kit.playbooks.prefix', '')).$playbook->name;
    }
}
