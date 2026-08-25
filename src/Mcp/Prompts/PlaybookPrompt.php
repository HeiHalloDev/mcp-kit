<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Mcp\Prompts;

use HeiHallo\McpKit\Contracts\PlaybookStore;
use HeiHallo\McpKit\Playbooks\Playbook;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

/**
 * One saved playbook, offered as a prompt. Built per request from the
 * caller's own playbooks, so a client lists exactly what that person saved
 * (plus what colleagues shared) and nothing else.
 */
class PlaybookPrompt extends Prompt
{
    public function __construct(protected Playbook $playbook)
    {
        $this->name = ((string) config('mcp-kit.playbooks.prefix', '')).$playbook->name;
        $this->title = $playbook->title;
        $this->description = $this->describe($playbook);
    }

    public function playbook(): Playbook
    {
        return $this->playbook;
    }

    public function arguments(): array
    {
        return array_map(
            fn (array $argument): Argument => new Argument(
                name: $argument['name'],
                description: $argument['description'],
                required: $argument['required'],
            ),
            $this->playbook->arguments,
        );
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user !== null) {
            app(PlaybookStore::class)->recordUse($user, $this->playbook->name);
        }

        $values = [];

        foreach ($this->playbook->arguments as $argument) {
            $values[$argument['name']] = $request->get($argument['name']);
        }

        $missing = array_values(array_filter(
            $this->playbook->arguments,
            fn (array $argument): bool => $argument['required']
                && (! isset($values[$argument['name']]) || (string) $values[$argument['name']] === ''),
        ));

        if ($missing !== []) {
            return Response::error(sprintf(
                'This playbook needs %s. Ask for %s, then run it again.',
                implode(', ', array_column($missing, 'name')),
                count($missing) === 1 ? 'it' : 'them',
            ));
        }

        return Response::text(view('mcp-kit::prompts.playbook', [
            'playbook' => $this->playbook,
            'steps' => $this->playbook->render($values),
        ])->render());
    }

    protected function describe(Playbook $playbook): string
    {
        return $playbook->shared && $playbook->author !== null
            ? sprintf('%s (shared by %s)', $playbook->description, $playbook->author)
            : $playbook->description;
    }
}
