{{--
    A code block with a copy button in its corner.

    The clipboard read is the point: these lines carry a token, run past the
    width of the box, and lose their tail when selected by hand — which then
    looks like a broken server rather than a bad paste.

    Styled inline on purpose. A host app's Tailwind only generates the classes
    it scans, and an app that has not added this package to its @source list
    would otherwise drop the positioning and leave the button over the code.

    min-width: 0 is load-bearing. Dropped inside a grid or flex parent — a
    flux:field is one in every app that still carries the old starter kit's
    `[data-flux-field] { @apply grid gap-2 }` — the outer div is an item whose
    automatic minimum size is its min-content width, so an unwrappable line
    widens the track instead of scrolling inside it and the block leaves the
    page. A definite minimum ends that; in a plain block parent it does nothing.

    Expects: $code. Optional: $label, $copyLabel, $wrap.

    Pass $wrap for prose — the opening message, the AGENTS.md lines. A
    paragraph read by scrolling sideways is not read. Commands, JSON and TOML
    leave it off: a shell line broken across rows invites a bad hand-paste.
--}}
@php($snippetLabel = $label ?? null)
@php($snippetCopyLabel = $copyLabel ?? __('Copy'))
@php($snippetWrap = ($wrap ?? false) ? 'white-space: pre-wrap; overflow-wrap: anywhere; ' : '')

<div style="min-width: 0;">
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
        <pre style="{{ $snippetWrap }}overflow-x: auto; border-radius: 0.5rem; background-color: #09090b; padding: 1rem; padding-right: 3.25rem; font-size: 0.75rem; line-height: 1.6; color: #f4f4f5; margin: 0;"><code x-ref="code" style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace;">{{ $code }}</code></pre>

        @include('mcp-kit::tokens.copy-button', [
            'copyLabel' => $snippetCopyLabel,
            'style' => 'position: absolute; top: 0.5rem; right: 0.5rem;',
        ])
    </div>
</div>
