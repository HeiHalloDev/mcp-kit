@if ($description->knowsTeam() || $memory->team || $description->inboxes !== [])

## Where you work
@if ($description->knowsTeam())
- Team: {!! $description->team !!}
@elseif ($memory->team)
- Team (in your words): {!! $memory->team !!}
@endif
@if ($description->inboxes !== [])
- Inboxes: {!! implode(', ', $description->inboxes) !!}
@endif
@endif
@if (($builder ?? null) !== null)

## Who runs this app
- {!! $builder !!}. To tell them something: `send_message` to "dev". To ask for a tool, a fix or access: `report_gap`.
@endif
