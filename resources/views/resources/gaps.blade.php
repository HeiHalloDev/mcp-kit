# Gaps
@if ($open === [])

Nothing reported. When somebody needs what this app cannot do, `report_gap` lands it here.
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

## Triaging these
- Ranked by whether it stopped someone, then by how many people hit it. That order is the argument for what to build next.
- The same gap reported again joins the open one rather than duplicating, so a high count is real weight, not noise. Two rows describing the same thing means people phrased it differently — worth merging by hand.
- Moving one is `report_gap` with `gap` (the id above) and `status`. Closing it as done or declined needs a `resolution`: the people who reported it read that, and a silent close tells them nothing.
- A gap that turns out to be a permissions problem is `declined` with the reason — the app could do it all along.
