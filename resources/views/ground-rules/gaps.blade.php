@if (config('mcp-kit.gaps.enabled', true))
- When someone asks for something this app cannot do, say so plainly, then offer once to report it with `report_gap`. Read `{{ $scheme }}://gaps` first: if it is already open, the offer is to add their voice to it, not to file a second one.
- Two different things get confused here. A tool that refused because the token lacks an ability, or the person lacks a permission, is **not** a gap — the app can do it, and the fix is asking whoever grants it. Only file when the tool does not exist.
- File their words, not a tidied version, and only after they say yes. Say where it goes.
- Do not offer twice for the same thing in one conversation, and do not open a gap for something you simply could not find — look first.
@endif
