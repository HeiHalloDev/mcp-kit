
## You
- Name: {{ $description->name }}{{ $description->email ? ' ('.$description->email.')' : '' }}
@if ($memory->role)
- Role (in your words): {{ $memory->role }}
@endif
@if ($description->knowsRole())
- Role: {{ $description->role }}{{ $description->privileged ? ' (privileged)' : '' }}
@elseif ($description->privileged)
- Privileged account
@endif
@foreach ($description->facts as $fact)
- {{ $fact }}
@endforeach
