{{--
    Where to get the client itself.

    The tab above hands somebody a line to paste into a terminal they may
    never have opened. The download link and the one line that installs it
    belong next to it, not in a message somebody has to go and find.

    Expects: $client — a key of HeiHallo\McpKit\Tokens\ClientSetup::all().
--}}
@php($setup = \HeiHallo\McpKit\Tokens\ClientSetup::all()[$client] ?? null)

@if ($setup)
    <div class="space-y-3">
        <flux:text size="sm">{{ __('Do not have :client yet?', ['client' => $setup['title']]) }}</flux:text>
        <flux:text size="sm" class="opacity-70">{{ $setup['summary'] }}</flux:text>

        @if ($setup['download'])
            <flux:text size="sm">
                <flux:link href="{{ $setup['download']['url'] }}" target="_blank" rel="noopener noreferrer">{{ $setup['download']['label'] }}</flux:link>
            </flux:text>
        @endif

        @foreach ($setup['install'] as $platform => $command)
            @include('mcp-kit::tokens.snippet', ['code' => $command, 'label' => $platform])
        @endforeach

        @if ($setup['verify'])
            @include('mcp-kit::tokens.snippet', ['code' => $setup['verify'], 'label' => __('Check it is there — it prints a version number')])
        @endif

        <flux:text size="sm" class="opacity-70">
            {{ __('The vendor\'s own instructions, if any of this has moved:') }}
            <flux:link href="{{ $setup['docs']['url'] }}" target="_blank" rel="noopener noreferrer">{{ $setup['docs']['label'] }}</flux:link>
        </flux:text>
    </div>
@endif
