{{--
    A block of text with a copy button.

    The clipboard read is the point: these lines carry a token, run past the
    width of the box, and are easy to clip when selected by hand.

    Expects: $code. Optional: $label, $copyLabel.
--}}
@php($snippetLabel = $label ?? null)
@php($snippetCopyLabel = $copyLabel ?? __('Copy'))

<div x-data="{
    copied: false,
    copy() {
        navigator.clipboard.writeText(this.$refs.code.textContent.trim()).then(() => {
            this.copied = true;
            setTimeout(() => this.copied = false, 1600);
        });
    },
}">
    @if ($snippetLabel)
        <flux:text size="sm" class="mb-2">{{ $snippetLabel }}</flux:text>
    @endif

    <div class="relative">
        <pre class="overflow-x-auto rounded-lg bg-zinc-900 p-4 pe-24 text-xs text-zinc-100"><code x-ref="code">{{ $code }}</code></pre>

        <flux:button
            type="button"
            size="xs"
            icon="clipboard"
            x-on:click="copy()"
            class="absolute end-2 top-2 !bg-zinc-800 !text-zinc-200 hover:!bg-zinc-700"
        >
            <span x-show="! copied">{{ $snippetCopyLabel }}</span>
            <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
        </flux:button>
    </div>
</div>
