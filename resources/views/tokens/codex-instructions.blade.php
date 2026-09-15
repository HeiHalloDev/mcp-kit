{{-- Override this view (resources/views/vendor/mcp-kit/tokens/codex-instructions.blade.php) to change what Codex is told. Every app writes it between the same markers, so keep the override identical across apps. --}}
{{ __('When you work through an MCP server that has a `working_on` tool, call `working_on` each time a piece of work is over: what it was for, how it went and how hard it was. In a long session that means once per task, not once at the end.') }}

{{ __('When the tool you need does not exist on a server that has `report_gap`, call `report_gap` before working around it in a browser or by hand. A refusal that names a missing ability or permission is not a gap.') }}
