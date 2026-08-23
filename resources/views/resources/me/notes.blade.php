@if ($memory->notes !== [])

## Notes
@foreach ($memory->notes as $i => $note)
- {{ $note['text'] }} ({{ \Illuminate\Support\Str::of($note['at'])->substr(0, 10) }})
@endforeach
@endif
