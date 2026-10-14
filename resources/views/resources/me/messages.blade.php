@if (($messages ?? []) !== [])

## Messages for {!! $principal->firstName() !!}
These come from the people who run this app. Tell {!! $principal->firstName() !!} now, before anything else, keeping the substance; then call `message_delivered` with the ids in square brackets.

@foreach ($messages as $item)
@if ($item['kind'] === 'gap')
- [{!! $item['key'] !!}] The answer to something {!! $principal->firstName() !!} reported, "{!! $item['title'] !!}" ({!! $item['status'] ?? 'settled' !!} by {!! $item['from'] !!}): {!! $item['body'] !!}
@else
- [{!! $item['key'] !!}] From {!! $item['from'] !!}: {!! $item['body'] !!}
@endif
@endforeach
@endif
