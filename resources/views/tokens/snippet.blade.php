{{--
    A code block with a copy button in its corner.

    The clipboard read is the point: these lines carry a token, run past the
    width of the box, and lose their tail when selected by hand — which then
    looks like a broken server rather than a bad paste.

    Styled inline on purpose. A host app's Tailwind only generates the classes
    it scans, and an app that has not added this package to its @source list
    would otherwise drop the positioning and leave the button over the code.

    Expects: $code. Optional: $label, $copyLabel.
--}}
@php($snippetLabel = $label ?? null)
@php($snippetCopyLabel = $copyLabel ?? __('Copy'))

<div>
    @if ($snippetLabel)
        <flux:text size="sm" class="mb-2">{{ $snippetLabel }}</flux:text>
    @endif

    <div
        style="position: relative;"
        x-data="{
            copied: false,
            hovered: false,
            copy() {
                navigator.clipboard.writeText(this.$refs.code.textContent.trim()).then(() => {
                    this.copied = true;
                    setTimeout(() => this.copied = false, 1600);
                });
            },
        }"
    >
        <pre style="overflow-x: auto; border-radius: 0.5rem; background-color: #09090b; padding: 1rem; padding-right: 3.25rem; font-size: 0.75rem; line-height: 1.6; color: #f4f4f5; margin: 0;"><code x-ref="code" style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace;">{{ $code }}</code></pre>

        <button
            type="button"
            x-on:click="copy()"
            x-on:mouseenter="hovered = true"
            x-on:mouseleave="hovered = false"
            x-bind:title="copied ? '{{ __('Copied') }}' : '{{ $snippetCopyLabel }}'"
            x-bind:aria-label="copied ? '{{ __('Copied') }}' : '{{ $snippetCopyLabel }}'"
            x-bind:style="{
                backgroundColor: hovered ? 'rgba(255,255,255,0.18)' : 'rgba(255,255,255,0.08)',
                color: copied ? '#4ade80' : '#d4d4d8',
            }"
            style="position: absolute; top: 0.5rem; right: 0.5rem; display: inline-flex; align-items: center; justify-content: center; width: 1.875rem; height: 1.875rem; border: 1px solid rgba(255,255,255,0.14); border-radius: 0.375rem; cursor: pointer;"
        >
            <svg x-show="! copied" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 1rem; height: 1rem;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
            </svg>
            <svg x-show="copied" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1rem; height: 1rem;">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
        </button>
    </div>
</div>
