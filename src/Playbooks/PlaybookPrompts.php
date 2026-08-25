<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Playbooks;

use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\PlaybookStore;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Mcp\Prompts\PlaybookPrompt;
use Illuminate\Contracts\Auth\Factory as Auth;
use Throwable;

/**
 * The playbook prompts to append to a server for whoever is calling right
 * now: their own and the shared ones, minus what this server or this
 * token cannot run.
 */
class PlaybookPrompts
{
    /**
     * A missing table is reported once, not on every call.
     */
    private static bool $reported = false;

    public function __construct(
        protected Auth $auth,
        protected PrincipalResolver $principals,
        protected AbilityCatalogue $catalogue,
    ) {}

    /**
     * @return list<PlaybookPrompt>
     */
    public function for(?string $server): array
    {
        if (! config('mcp-kit.playbooks.enabled', true)) {
            return [];
        }

        // A server also boots outside a request (docs, the registry, a local
        // stdio session), where there is nobody to have playbooks.
        $user = $this->auth->guard()->user();

        if ($user === null) {
            return [];
        }

        $principal = $this->principals->resolve($user);

        if ($principal === null || ! $principal->isPerson() || $principal->blocked) {
            return [];
        }

        $granted = $this->catalogue->expand($principal->abilities());

        try {
            $playbooks = app(PlaybookStore::class)->visibleTo($user);
        } catch (Throwable $e) {
            // A missing table must not take the whole server down; the app
            // has simply not migrated yet.
            if (! self::$reported) {
                self::$reported = true;
                report($e);
            }

            return [];
        }

        $prompts = [];

        foreach ($playbooks as $playbook) {
            if ($playbook->appliesTo($server) && $playbook->runnableWith($granted)) {
                $prompts[] = new PlaybookPrompt($playbook);
            }
        }

        return $prompts;
    }
}
