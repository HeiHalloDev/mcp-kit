@if ($memory->routines !== [] || $memory->handoffs !== [])

## How {{ $principal->firstName() }} usually works
Today may differ; help with what is asked.
@foreach ($memory->routines as $routine)
- Usually: {{ $routine }}
@endforeach
@foreach ($memory->handoffs as $handoff)
- Hands off: {{ $handoff }}
@endforeach
@endif
