@if ($settled !== [])

## What came of what you asked for
@foreach ($settled as $gap)
@php
    $outcome = match ($gap->status) {
        'done' => 'built: '.($gap->resolution ?? 'it works now'),
        'declined' => 'not doing it: '.($gap->resolution ?? 'no reason recorded'),
        default => 'on the list to build',
    };
@endphp
- **{{ $gap->title }}** — {{ $outcome }}
@endforeach

Pass this on once, in a line, at a natural moment: it answers something {{ $principal->firstName() }} raised, and it is not the topic of the session. It will not be shown again.
@endif
