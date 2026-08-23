
## What you may do here
@if ($description->permissions !== [])
Permissions: {{ implode(', ', $description->permissions) }}.
@endif
@if ($servers !== [])
Servers this token reaches: {{ implode(', ', $servers) }}.
@endif
@if ($can !== [])
This token can read:
@foreach ($can as $ability)
- {{ $descriptions[$ability] }} (`{{ $ability }}`)
@endforeach
@endif
@if ($canChange !== [])
This token can change:
@foreach ($canChange as $ability)
- {{ $descriptions[$ability] }} (`{{ $ability }}`)
@endforeach
@else
This token cannot change anything. Ask the person to mint a token with write access if they need to.
@endif
@if ($expiresAt)
Token expires {{ $expiresAt->toDateString() }}.
@endif
