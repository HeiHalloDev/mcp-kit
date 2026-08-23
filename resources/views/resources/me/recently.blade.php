@if ($recent['calls'] > 0)

## Recently (last {{ $recent['days'] }} days)
- {{ $recent['calls'] }} tool calls
@if ($recent['top_tools'] !== [])
- Most used: {{ implode(', ', array_map(fn ($t) => $t['tool'].' ('.$t['calls'].')', $recent['top_tools'])) }}
@endif
@foreach ($recent['last_changes'] as $change)
- Changed: {{ $change['action'] }} ({{ $change['at'] }})
@endforeach
@endif
