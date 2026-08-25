# Playbooks
@if ($mine === [] && $theirs === [])

Nothing saved yet. When {{ $principal->firstName() }} works out a way of doing something and would want it back, offer to save it with `save_playbook` — once, and only if they say yes.
@else
@if ($mine !== [])

## Saved by {{ $principal->firstName() }}
@foreach ($mine as $playbook)

### {{ $prefix }}{{ $playbook->name }} — {{ $playbook->title }}
{{ $playbook->description }}
@if ($playbook->arguments !== [])
- Takes: @foreach ($playbook->arguments as $argument){{ $argument['name'] }}{{ $argument['required'] ? ' (required)' : '' }}{{ ! $loop->last ? ', ' : '' }}@endforeach

@endif
@if ($playbook->abilities !== [])
- Needs: {{ implode(', ', $playbook->abilities) }}@if (! $playbook->runnableWith($granted)) — **this token cannot run it**@endif

@endif
@if ($playbook->servers !== [])
- On: {{ implode(', ', $playbook->servers) }}
@endif
- Used {{ $playbook->uses === 0 ? 'never yet' : $playbook->uses.' times' }}{{ $playbook->shared ? ', shared with everyone' : '' }}

{{ $playbook->body }}
@endforeach
@endif
@if ($theirs !== [])

## Shared by colleagues
@foreach ($theirs as $playbook)

### {{ $prefix }}{{ $playbook->name }} — {{ $playbook->title }}
{{ $playbook->description }}@if ($playbook->author !== null) Saved by {{ $playbook->author }}.@endif

@if ($playbook->abilities !== [] && ! $playbook->runnableWith($granted))
- Needs {{ implode(', ', $playbook->abilities) }} — **this token cannot run it**
@endif

{{ $playbook->body }}
@endforeach
@endif
@endif

## Using them
- Run one by name; the client lists them as prompts.
- The steps are a starting point, not a script. Do what is asked today.
- To change one, `save_playbook` under the same name. To remove one, `save_playbook` with `delete=true`. Someone else's shared playbook is theirs — save your own version under a different name instead.
