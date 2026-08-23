@if ($memory->preferences !== [])

## Preferences
@foreach ($memory->preferences as $key => $value)
- {{ $key }}: {{ is_bool($value) ? ($value ? 'yes' : 'no') : $value }}
@endforeach
@endif
