@if ($service)
# No person, no profile

This token belongs to the service client **{{ $principal->name }}**. There is no person behind it, so there is nothing to remember and nothing to ask. Service clients may read, and write only what the catalogue allows.
@else
# {{ $principal->name }}
@include('mcp-kit::resources.me.you')
@include('mcp-kit::resources.me.where')
@include('mcp-kit::resources.me.can')
@include('mcp-kit::resources.me.usually')
@include('mcp-kit::resources.me.preferences')
@include('mcp-kit::resources.me.notes')
@include('mcp-kit::resources.me.recently')
@include('mcp-kit::resources.me.memory')
@include('mcp-kit::resources.me.footer')
@endif
