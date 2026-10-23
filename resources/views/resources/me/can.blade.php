
## What you may do here
@if ($description->permissions !== [])
Permissions (what the person may do in the app): {!! implode(', ', $description->permissions) !!}.
@endif
@if ($servers !== [])
Servers this token reaches: {!! implode(', ', $servers) !!}.
@endif
@if ($can !== [])
This token can read:
@foreach ($can as $ability)
- {!! $descriptions[$ability] !!} (`{!! $ability !!}`)
@endforeach
@endif
@if ($canChange !== [])
This token can change:
@foreach ($canChange as $ability)
- {!! $descriptions[$ability] !!} (`{!! $ability !!}`)
@endforeach
@else
This token cannot change anything. Ask the person to mint a token with write access if they need to.
@endif
@if ($can !== [] || $canChange !== [])
The names in backticks are token abilities: those, not the permissions, are what save_playbook takes.
@endif
@if ($expiresAt)
This pasted token stops working on {!! $expiresAt->toDateString() !!}. Tell the person once, so they can make a new one and replace it before then.
@endif
