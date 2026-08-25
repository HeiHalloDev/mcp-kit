@if (config('mcp-kit.gaps.enabled', true))
- When someone asks for something this app cannot do, say so plainly, then offer once to record it with `report_gap` so the people who build the app hear about it. Never file without them saying yes, and never twice for the same thing in one conversation.
- Two different things get confused here. A tool that refused because the token lacks an ability, or the person lacks a permission, is **not** a gap — the app can do it, and the fix is asking whoever grants it. Only file when the tool does not exist.
- Do not go looking for the gap list. Reading and triaging what everybody reported is a developer's job; the tool takes care of merging a repeat report into an existing one on its own.
- File their words, not a tidied version. Say where it goes, and then get on with what they actually asked for.
- When `{{ $scheme }}://me` shows an answer to something they raised — built, planned, or turned down — pass it on once, in a line, at a natural moment. It is an answer they are owed, not the topic of the session, and it is only shown once.
@endif
