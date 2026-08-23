@if ($description->knowsTeam() || $memory->team || $description->inboxes !== [])

## Where you work
@if ($description->knowsTeam())
- Team: {{ $description->team }}
@elseif ($memory->team)
- Team (in your words): {{ $memory->team }}
@endif
@if ($description->inboxes !== [])
- Inboxes: {{ implode(', ', $description->inboxes) }}
@endif
@endif
