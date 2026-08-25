{{ $playbook->title }}
@if ($playbook->shared && $playbook->author !== null)

Saved by {{ $playbook->author }} and shared with the team.
@endif

## What this is for
{{ $playbook->description }}

## Steps
{{ $steps }}

## How to run it
- These are the person's own steps, written down when the work went well. Follow them in order.
- They are a starting point, not a script. If today's request differs, do what is asked and say what you changed.
@if ($playbook->placeholders() !== [])
- A `@{{ placeholder }}` still showing means nobody filled it in. Ask for it before that step, do not guess.
@endif
- Writes still preview first and still need a yes. A saved playbook never counts as advance permission.
- If a step no longer matches the app, say so once and offer to update the playbook with `save_playbook`.
