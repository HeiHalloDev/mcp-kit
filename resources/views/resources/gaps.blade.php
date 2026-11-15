# Gaps
@if ($open === [])

Nothing reported. When somebody needs what this app cannot do, `report_gap` lands it here.
@else

## Open — most wanted first
@foreach ($open as $gap)

### {!! $gap->id !!}. {!! $gap->title !!}{!! $gap->status === 'planned' ? ' (planned)' : '' !!}
- Needed: {!! $gap->need !!}
- Missing: {!! $gap->missing !!}
- {!! $gap->reports !!} {!! $gap->reports === 1 ? 'person has' : 'people have' !!} hit it{!! $gap->blocking ? ', and it stopped the work' : '' !!}@if ($gap->server ?? false) — on `{!! $gap->server !!}`@endif

@if ($gap->reportedAt)
- Reported {!! $gap->reportedAt->format('Y-m-d') !!}{!! $gap->status === 'planned' && $gap->resolvedAt ? ', planned '.$gap->resolvedAt->format('Y-m-d') : '' !!}{!! $gap->lastFollowUpAt() ? ', last follow-up '.$gap->lastFollowUpAt() : '' !!}
@endif
@foreach ($gap->reporters as $reporter)
- {!! $reporter['name'] !!}@if ($reporter['at'] !== ''), {!! \Illuminate\Support\Str::before($reporter['at'], 'T') !!}@endif@if ($reporter['note'] !== ''): {!! $reporter['note'] !!}@endif

@endforeach
@if (filled($gap->resolution))
- {!! $gap->status === 'planned' ? 'Planned' : 'Written on it while open' !!}@if ($gap->resolvedBy) by {!! $gap->resolvedBy !!}@endif: {!! $gap->resolution !!}
@endif
@if ($gap->followUps !== [])
- Follow-ups, oldest first:
@foreach ($gap->followUps as $followUp)
  - {!! $followUp['name'] !!}, {!! \HeiHallo\McpKit\Gaps\Gap::stamp($followUp['at']) !!}: {!! $followUp['text'] !!}
@endforeach
@endif
@endforeach
@endif
@if ($closed !== [])

## Settled recently
@foreach ($closed as $gap)
- **{!! $gap->id !!}. {!! $gap->title !!}** — {!! $gap->status === 'done' ? 'built' : 'not doing it' !!}@if ($gap->resolvedAt) {!! $gap->resolvedAt->format('Y-m-d') !!}@endif@if ($gap->resolvedBy) by {!! $gap->resolvedBy !!}@endif: {!! $gap->resolution ?? 'no reason recorded' !!}
@foreach ($gap->followUps as $followUp)
  - Follow-up, {!! $followUp['name'] !!}, {!! \HeiHallo\McpKit\Gaps\Gap::stamp($followUp['at']) !!}: {!! $followUp['text'] !!}
@endforeach
@endforeach
@endif

## Triaging these
- Ranked by whether it stopped someone, then by how many people hit it. That order is the argument for what to build next. Each one carries the date it came in and the dates the people behind it reported, so "what arrived today" is a read, not a database query.
- The same gap reported again joins the open one rather than duplicating, so a high count is real weight, not noise. Two rows describing the same thing means people phrased it differently: `report_gap` with `gap` (the duplicate) and `merge_into` (the one it repeats) moves the reporters across and closes the duplicate pointing at it.
- Moving one is `report_gap` with `gap` (the id above) and `status`. Closing it as done or declined needs a `resolution`: the people who reported it read that, and a silent close tells them nothing. Re-stating the same status with a new resolution rewords what they will read; on a gap that is still open there is no decision to reword, so the words are kept as a follow-up.
- Something to ask the people who reported it, or to tell them before it is decided, is a follow-up: `report_gap` with `gap` and `note`. It is dated, kept in order beside the report and reaches them on their next tool call; what they write back reaches you the same way and is listed under the gap. A decision is still a `status` with a `resolution`.
- Something already answered elsewhere can be filed and settled in one call: `report_gap` with the report, plus `status` and `resolution`.
- A gap that turns out to be a permissions problem is `declined` with the reason — the app could do it all along.
