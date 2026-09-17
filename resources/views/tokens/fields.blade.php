{{--
    One value per row, each with its own copy button — for a form that takes
    the values one field at a time (the ChatGPT app's MCP form), where a single
    block means selecting every value by hand.

    Styled inline for the same reason as the snippet partial.

    Expects: $fields (field label => value). Optional: $label.

    No min-width: 0 here, unlike the snippet partial. A row is a fixed label
    column, a value and a button: its min-content is a floor, not a runaway
    line, and removing it would spill the label and the button out of the
    dark box on a narrow screen. The two partials differ on purpose.
--}}
@php($fieldsLabel = $label ?? null)

<div>
    @if ($fieldsLabel)
        <flux:text size="sm" class="mb-2">{{ $fieldsLabel }}</flux:text>
    @endif

    <div style="border-radius: 0.5rem; background-color: #09090b; padding: 0.5rem 0.5rem 0.5rem 1rem; font-size: 0.75rem; line-height: 1.6; color: #f4f4f5; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;">
        @foreach ($fields as $fieldLabel => $fieldValue)
            <div
                style="display: flex; align-items: center; gap: 0.75rem; padding: 0.125rem 0;"
                x-data="{
                    copied: false,
                    hovered: false,
                    copy() {
                        navigator.clipboard.writeText(this.$refs.value.textContent.trim()).then(() => {
                            this.copied = true;
                            setTimeout(() => this.copied = false, 1600);
                        });
                    },
                }"
            >
                <span style="flex: none; width: 6.5rem; color: #a1a1aa;">{{ $fieldLabel }}</span>
                <code x-ref="value" style="flex: 1; min-width: 0; overflow-x: auto; white-space: nowrap; font-family: inherit;">{{ $fieldValue }}</code>
                @include('mcp-kit::tokens.copy-button', [
                    'copyLabel' => __('Copy :field', ['field' => $fieldLabel]),
                    'style' => 'flex: none;',
                ])
            </div>
        @endforeach
    </div>
</div>
