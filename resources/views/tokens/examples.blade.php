{{-- Override this view (resources/views/vendor/mcp-kit/tokens/examples.blade.php) with prompts in your staff's language, area by area. --}}
<flux:text size="sm" class="mb-4">{{ __('Things to ask the assistant. Phrase it however you like — it finds the right tool itself. Anything that changes data is previewed first and only executed when you say yes.') }}</flux:text>
<pre class="overflow-x-auto rounded-lg bg-zinc-900 p-4 text-xs leading-relaxed text-zinc-100"><code>{{ __('"What can you help me with here?"') }}
{{ __('"Find the record for kari@example.com and show me the history."') }}
{{ __('"Which of my tasks are due this week?"') }}</code></pre>
