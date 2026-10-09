@if (! empty($newTools))
## New since you connected
Connected {!! $connectedAt?->format('j M Y') !!}. Added here since then, and yours to use:
@foreach ($newTools as $row)
- `{!! $row['tool'] !!}` ({!! $row['since'] !!})
@endforeach

If they are not in your tool list, the client is still showing the list it saw when it connected (claude.ai and the desktop app keep it). Tell the person once, in a line: disconnect and connect again under Settings → Connectors, then start a new chat. Claude Code picks them up in a new session.
@endif
