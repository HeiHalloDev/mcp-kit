# Changelog

## v1.2.0 — 2026-08-25

Playbooks: a person saves the way they worked something out, and their client offers it back by name.

- `save_playbook` stores a recipe — title, one-line description, the steps, named `{{ placeholders }}` — under a name the person picks. Previews without `confirm=true` like every other write; `delete=true` removes one. Credentials are refused, a placeholder nobody declared is refused, and the cap is 30 per person.
- Each saved playbook is appended to every server the caller reaches as an MCP prompt, so Claude Code lists it as a slash command. Scope one to certain servers with `servers`, and to certain abilities with `abilities` — a token that cannot run it never sees it.
- `{scheme}://playbooks` lists what the person saved and what colleagues shared, with the steps, for reading rather than running.
- Sharing a playbook with everyone is privileged by default (`mcp-kit.playbooks.policy`). A shared playbook is read-only to colleagues: they save their own under another name.
- New table `mcp_playbooks`, new ground-rules section (offer once, never save without a yes), new events `PlaybookSaved` and `PlaybookForgotten`. Turn the whole thing off with `mcp-kit.playbooks.enabled`.

## v1.1.2 — 2026-08-25

- The connect snippet styles itself instead of trusting the host's Tailwind: a package view is only scanned when the app lists it in `@source`, so the copy button was landing on top of the code.

## v1.1.1 — 2026-08-25

- The copy button sits in the corner of the code block, and every server gets its own block as well as the combined one.

## v1.1.0 — 2026-08-25

- Tokens page: minting moved behind an "Add token" button that closes on success, every connect snippet became a code block with a copy button, and `claude mcp remove` lines joined the `claude mcp add` ones.

## v1.0.0 — 2026-08-24

Stable. The CRM, TrustMe, flex, io, studies and L5 all run on the kit.

- The `note` alias in preview responses is gone; read `next`.
- Since 0.3: preset-driven labels and guards (0.3.4/0.3.5/0.3.8), the wildcard-privilege knob (0.3.5), staff refusals name the missing permission (0.3.7).

## v0.3.5 — 2026-08-24

- `tokens.wildcards_require_privileged` (default true): turn off when every staff member may hold the server wildcard.
- The guard tests find the reads/wildcards presets by grant kind instead of assuming the keys read/full.

## v0.3.4 — 2026-08-24

- Token labels follow the app's own preset names: the wildcard label comes from the first `wildcards` preset, the read label from the first `reads` preset, and so on — read/support/admin works as well as read/work/full.

## v0.3.3 — 2026-08-23

- Tokens page: the lifetime is a choice list (30/90/180/365 days, capped by `tokens.max_days`, the default marked "recommended") instead of a number field.

## v0.3.2 — 2026-08-23

- Channel resolution outside a call: a signed-in user stamps `web`, a personal token `api`, an artisan command `cli`, everything unattended (jobs, webhooks, tests) `system`.
- `ActivityChannel::color()`.
- The lost-permission refusal reads "does not hold the '…' permission".
- Guards: the full preset need not reach a server that opted out of presets (v0.3.1).

## v0.3.0 — 2026-08-23

- `mcp-kit.docs.renderer`: the generated docs blocks come from a `DocsRenderer`; the default adds an ability matrix between `generated:abilities` markers when a document has them.
- `servers.*.presets = false` keeps a server's abilities out of the token presets; `servers.*.shared = false` keeps the shared primitives and the instructions footer off a server (customer-facing servers). `tokens.staff_only` makes presets staff-only.
- `ActivityChannel::options()`.

## v0.2.0 — 2026-08-23

- `ConfirmsWrites` opens the call context itself when no HTTP middleware did (in-app agents on the web guard, direct invocations, tests), so domain rows written during a confirmed write always share the `call_id`; the channel is `chat` when the caller has no personal token.
- `ConfirmsWrites::recordWrite()` and `writeWasRecorded()` are available to base classes that make the audit unconditional; `withinCall()` wraps any execution in a context.
- `McpCallContext` carries the channel; `DefaultChannelResolver` and the stamper read it. The audit writer no longer forces columns on its rows — the stamper fills them like on every other row.

## v0.1.0 — 2026-08-23

First release. One package for the staff-tooling foundation every HeiHallo app used to copy by hand.

- Ability catalogue and token presets from config, with server and family wildcards, explicit-only abilities, legacy aliases and service-client writes.
- `McpKit::server()` / `Mcp::staff()`: every server behind `auth:sanctum`, a per-token throttle, `EnsureMcpAccess` and `AuditMcpCall`. An `Mcp::web` route without the guards refuses to boot.
- `StaffServer` base: authored instructions plus the kit footer, shared primitives appended, read-only mode.
- Tool concerns: `ChecksAbilities`, `ConfirmsWrites` (one preview/confirm shape), `DeclaresInputSchema`, `LinksToAdmin`, `ResolvesPrincipal`; `StaffTool` bundles them.
- Every tool call and every confirmed write is an `activity_log` row in the `mcp` log; `source`, `channel` and `token_name` columns stamped on all activity rows.
- `{scheme}://ground-rules`, `{scheme}://me`, `remember_about_me`, `getting_started`; `users.assistant_memory`.
- Commands: `mcp:install`, `mcp:token`, `mcp:client-token`, `mcp:docs`, `mcp:audit-tokens`, `mcp-kit:prune`.
- Optional Livewire + Flux tokens page and assistant-memory section.
- `Testing\Guards::all()` for apps; Testbench suite on Postgres across Laravel 12/13 and activitylog 4/5.

The `note` key in preview responses is an alias of `next` and goes away in v1.0.
