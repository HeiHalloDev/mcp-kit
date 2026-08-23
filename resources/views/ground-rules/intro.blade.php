@if ($intro)
{{ $intro }}
@else
What this app is: {{ $appName }}. These tools let staff work in it through an assistant. Content may be in the staff's own language; tool labels are English.
@endif
@if (count($servers) > 1)

Servers:
@foreach ($servers as $definition)
- **{{ $definition->label }}** (`{{ $definition->clientName }}`){{ $definition->description !== '' ? ': '.$definition->description : '' }}
@endforeach
@endif
