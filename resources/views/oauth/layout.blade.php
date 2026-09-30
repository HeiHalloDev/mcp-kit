{{--
    The frame around the sign-in pages. Standalone on purpose: the kit has no
    asset pipeline of its own, so the few styles it needs are inline. An app
    that wants its own chrome publishes the views (mcp-kit-views) and replaces
    this file, or points oauth.layout at a Blade component of its own.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? config('app.name') }}</title>
    <style>
        :root { color-scheme: light dark; --bg: #f4f4f5; --card: #fff; --text: #18181b; --muted: #71717a; --border: #e4e4e7; --accent: #18181b; --accent-text: #fff; }
        @media (prefers-color-scheme: dark) { :root { --bg: #09090b; --card: #18181b; --text: #fafafa; --muted: #a1a1aa; --border: #3f3f46; --accent: #fafafa; --accent-text: #18181b; } }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; background: var(--bg); color: var(--text); font: 15px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
        main { width: 100%; max-width: 460px; background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 28px; }
        h1 { font-size: 20px; line-height: 1.3; margin: 0 0 8px; }
        p { margin: 0 0 12px; }
        .muted { color: var(--muted); font-size: 14px; }
        ul { list-style: none; padding: 0; margin: 16px 0; border: 1px solid var(--border); border-radius: 10px; }
        li { padding: 10px 12px; border-top: 1px solid var(--border); }
        li:first-child { border-top: 0; }
        label { display: flex; gap: 10px; align-items: flex-start; cursor: pointer; }
        input[type=checkbox] { margin-top: 4px; }
        .tag { display: inline-block; font-size: 12px; padding: 0 6px; border: 1px solid var(--border); border-radius: 6px; color: var(--muted); margin-left: 4px; }
        .actions { display: flex; gap: 10px; margin-top: 20px; }
        button { flex: 1; font: inherit; padding: 10px 14px; border-radius: 10px; border: 1px solid var(--border); background: transparent; color: var(--text); cursor: pointer; }
        button.primary { background: var(--accent); color: var(--accent-text); border-color: var(--accent); }
    </style>
</head>
<body>
    <main>
        {{ $slot }}
    </main>
</body>
</html>
