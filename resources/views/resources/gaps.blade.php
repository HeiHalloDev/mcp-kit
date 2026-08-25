# Gaps
@if ($open === [])

Nothing open. When someone needs something this app cannot do, `report_gap` puts it in front of the people who build it.
@else

## Open — most wanted first
@foreach ($open as $gap)

### {{ $gap->id }}. {{ $gap->title }}{{ $gap->status === 'planned' ? ' (planned)' : '' }}
- Needed: {{ $gap->need }}
- Missing: {{ $gap->missing }}
- {{ $gap->reports }} {{ $gap->reports === 1 ? 'person has' : 'people have' }} hit it{{ $gap->blocking ? ', and it stopped the work' : '' }}@if ($gap->server ?? false) — on `{{ $gap->server }}`@endif

@foreach ($gap->reporters as $reporter)
@if ($reporter['note'] !== '')
  - {{ $reporter['name'] }}: {{ $reporter['note'] }}
@endif
@endforeach
@endforeach
@endif
@if ($closed !== [])

## Settled recently
@foreach ($closed as $gap)
- **{{ $gap->title }}** — {{ $gap->status === 'done' ? 'built' : 'not doing it' }}: {{ $gap->resolution ?? 'no reason recorded' }}
@endforeach
@endif

## Before you file one
- A refusal that named a missing ability or permission is not a gap: the app can do it, this token may not. Say who to ask instead.
- If it is already open above, `report_gap` with the same title adds this person to it. Do not write a second one.
- Only after the person confirms. Their words, not a rewrite of them.
