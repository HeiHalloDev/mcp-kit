{{--
    A code block with a copy button in its corner.

    The clipboard read is the point: these lines carry a token, run past the
    width of the box, and lose their tail when selected by hand — which then
    looks like a broken server rather than a bad paste.

    Expects: $code. Optional: $label, $copyLabel.
--}}
@php($snippetLabel = $label ?? null)
@php($snippetCopyLabel = $copyLabel ?? __('Copy'))

<div>
    @if ($snippetLabel)
        <flux:text size="sm" class="mb-2">{{ $snippetLabel }}</flux:text>
    @endif

    <div
        class="relative"
        x-data="{
            copied: false,
            copy() {
                navigator.clipboard.writeText(this.$refs.code.textContent.trim()).then(() => {
                    this.copied = true;
                    setTimeout(() => this.copied = false, 1600);
                });
            },
        }"
    >
        <pre class="overflow-x-auto rounded-lg bg-zinc-900 p-4 pe-14 text-xs leading-relaxed text-zinc-100 dark:bg-zinc-950"><code x-ref="code">{{ $code }}</code></pre>

        <button
            type="button"
            x-on:click="copy()"
            x-bind:title="copied ? '{{ __('Copied') }}' : '{{ $snippetCopyLabel }}'"
            x-bind:aria-label="copied ? '{{ __('Copied') }}' : '{{ $snippetCopyLabel }}'"
            class="absolute end-2 top-2 rounded-md border border-white/10 bg-white/10 p-1.5 text-zinc-300 transition hover:bg-white/20 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white/40"
        >
            <span x-show="! copied" class="block">
                <flux:icon.clipboard-document class="size-4" />
            </span>
            <span x-show="copied" x-cloak class="block text-green-400">
                <flux:icon.check class="size-4" />
            </span>
        </button>
    </div>
</div>
