# Changelog

## v1.9.0 — 2026-08-27

An argument a tool does not declare is now refused instead of dropped.

- **`strict_parameters` (on by default).** Every `$request->get()` silently ignores an argument the tool never declared, and the tool then answers confidently about something else. The CRM's `get_available_slots`, handed `user_id` instead of `staff`, ignored it, fell back to the caller and reported that *they* had no booking calendar — the wrong person, for the wrong reason, with nothing to show anything had gone astray. The refusal names the unknown parameter, suggests the closest real one and lists them all.
- **People only.** Service clients are exempt: their calls are code we change deliberately, not a model guessing. An ability denial still wins, so a stray argument never reveals a schema to a token that may not use the tool.
- Off with `MCP_STRICT_PARAMETERS=false`; `always_allowed_parameters` (default `confirm`) is never counted as unknown.
- Redaction moved where it belongs: `Sanitizer` has unit tests, and the call-recording test now proves an undeclared secret is refused outright while a *declared* one (the CRM's `send_sms` `message`) is still redacted in the row.

## v1.8.0 — 2026-08-26

The first assistant to use `working_on` in earnest tried seven times and never got in. Every one of those refusals was logged as a successful read, and the frame it was trying to name looked exactly like one nobody had bothered with.

- **Every refusal names its parameter.** It fixed `effort` after a single refusal, because that message lists the valid values — and never found `purpose` or `result` across six attempts, because those messages asked for a thing without saying what it was called. Now: `` `result` is missing `` where it used to say "say what got in the way".
- **The names it reached for instead are accepted**: `task`, `work`, `doing`, `summary` for `purpose`; `friction`, `shortfall`, `reason`, `note`, `details` for `result`. The reply says which parameter the value went into, so the next call is right rather than merely forgiven.
- **Refusals are counted on the frame** (`mcp_tasks.refusals`, `Task::namingWasRefused()`) and lead `{scheme}://usage` and the usage page. A frame that bounced seven times and one nobody touched were indistinguishable, and they mean opposite things.
- **A tool that refuses is logged as `failed`.** `Response::error()` returns a *successful* JSON-RPC result carrying `isError`, which the transport-level check never saw, so every refusal read as `read`. A denial keeps its more precise `denied`.

## v1.7.0 — 2026-08-26

Servers can merge without stranding their clients, and presets know who they are for.

- **`alias_of` on a server entry.** When two servers merge, every client configured against the absorbed server's path is one deploy from an outage. An alias entry keeps the old path registered, serves the target's class, and makes every access decision against the target's key — a token passes at the alias exactly when it would pass at the server it points to, blocked-owner and staff gates included. Aliases are routing, not servers: the registry's new `canonical()` excludes them, and the catalogue, docs, inventory and summary counts reason over canonical servers only. Nothing offers an alias to a new client.
- **`roles` on a preset.** Permissions alone cannot separate two roles that hold the same key but deserve different token shapes — an owner and an external analyst may both hold `reports`. A preset may now name the roles it is for, matched through the same role reading the permission rules already use (spatie, enum-backed or string `role` attributes). No list means offered to everyone; privileged owners bypass every list — minting the narrower presets is how they test them.
- **The tokens page opens on the configured default preset.** `mount()` took the first key of the resolved presets and ignored `tokens.default_preset` — every app that configured `work` as its default was opening the picker on `read`. Behavior note: pages whose configured default differs from their first preset key open on a different radio after this; that is the configuration finally being honoured.
- The extended checkboxes label themselves with a readable name ("Users Admin") instead of the raw ability string, which moves into the description.

Compatibility: verified against the full suites of flex.afpt, io.afpt, studies.afpt, frontend.l5 and frontend.trustme with no config changes.

## v1.6.0 — 2026-08-26

The two records you could only read through an MCP client now have a page.

- **New page at `settings/mcp-usage`** (`ui.usage_page.enabled`, off by default): what the work was for, what fell short, what worked but fought back, and what nobody named — plus the gap list. Privileged only, like the resources it mirrors. It leads with the share of frames that got a purpose, which is the one number that measures whether anybody is naming their work.
- **A gap can finally be decided.** `Gap::PLANNED`, `DONE` and `DECLINED`, `settledFor()` and `markHeard()` all existed and nothing could reach them, so every reported gap stayed open forever and no reporter ever heard back. The page settles one with a required line saying what was decided; `GapStatusChanged` — which also already existed and was never dispatched — now fires, and everybody who reported it is marked unheard again, so planned and built are two separate pieces of news.

## v1.5.0 — 2026-08-26

Frames open themselves, and closing one carries a judgement of how hard it was.

- **Nobody opens a frame any more.** The middleware opens one on the first call and stamps every call after it. Two rounds of instructions asking the assistant to open one first produced **zero frames against ninety-six real calls** — opening one requires predicting that the work will matter, and models are bad at predicting and good at reacting. Grouping now happens whether anyone cooperates or not.
- `working_on` became *"say what that was"* rather than *"say what this will be"*. It names the open frame and closes it, and a purpose written at the end is better evidence than a guess written at the start.
- **New `effort` on close: `smooth | fiddly | fought_it`, required.** Call counts are a bad proxy for difficulty — read-before-write and preview-then-confirm make a correct write three calls by design. `done` with `fought_it` is the row the old schema could not express at all: it succeeded, so no outcome flags it and no count finds it. `{scheme}://usage` now lists those above the plain successes.
- The nudge to name a frame arrives in the result of the call itself, once, as its own content block — never appended to the tool's own text.
- Closing without a reason is refused for anything except a smooth success. Nullable column, so frames closed before this keep working.

## v1.4.2 — 2026-08-26

- The instruction to open a task frame moved into the connect-time instructions, gated on `learning.enabled`. It had lived only in the ground-rules resource, which an assistant reads late or not at all — so CRM recorded 28 tool calls and zero frames overnight. A frame has to be opened *before* the work, so the instruction has to arrive before the work too. Apps that do not record are unaffected.

## v1.4.1 — 2026-08-25

- `learning.enabled` reads `MCP_LEARNING_ENABLED`, the way `read_only` already reads `MCP_READ_ONLY`. The point of the switch is running it for a fortnight and turning it off again; that should not need a deploy.

## v1.4.0 — 2026-08-25

Task frames: the call log finally knows what the work was *for*, and whether it worked. Off by default — it records what your colleagues do all day, so turning it on is a decision.

- `working_on` opens a frame with one line on what the person wants, and closes it with an outcome: done, partly or failed. Every call in between is stamped with it by the existing activity stamper, so no tool needed a new parameter and no schema changed.
- Closing honestly is enforced where it matters: a `partly` or `failed` without saying what got in the way is refused. Those rows are the point of the feature.
- Opening a second frame closes an abandoned one as `unknown` rather than counting it as a success nobody vouched for; a frame older than `lifetime_hours` (4) ages out the same way. Frames belong to a token, so two people never share one.
- `{scheme}://usage` shows what people came to do lately and whether they got it, shortfalls first — privileged, like the gap list, because it is a record of colleagues' work. A repeat shortfall that is not on the gap list is the strongest signal in the kit.
- `{scheme}://me` tells the person plainly that this is on, what is kept and for how long. Recording what staff do without telling them is a different product.
- New table `mcp_tasks`, events `TaskOpened` and `TaskClosed` (`$task->fellShort()` is the one to listen for), and `mcp-kit:prune` takes frames with the calls they describe. Turn it on with `mcp-kit.learning.enabled`.

## v1.3.1 — 2026-08-25

Gap reports are a developer's list, not a staff one — and the people who report get an answer.

- `{scheme}://gaps` is privileged. Staff report with `report_gap`; reading what everybody reported, with names and their notes, is for whoever builds the app. Before this any token that reached a server could read the lot, including a narrow one held by somebody outside the team.
- The person who reported a gap hears what came of it: `{scheme}://me` shows the ones since built, planned or turned down, with the reason — once, then never again. The ground rules say pass it on in a line, not as the topic of the session.
- The ground rules no longer send the assistant to read the gap list first; the tool merges a repeat report on its own.

## v1.3.0 — 2026-08-25

Gap reports: when the app cannot do what someone needs, the assistant files it instead of the person having to remember afterwards.

- `report_gap` records what they were trying to do, what was missing, and whether it stopped the work. Previews without `confirm=true`; credentials refused; service clients have none.
- The same gap reported again gathers weight instead of duplicating: a matching open gap gains the second person and their note, and one person blocked makes the whole gap blocking. Reporting your own gap twice is refused.
- Privileged staff move a gap through open → planned → done/declined. Closing one without saying what was decided is refused — the people who reported it get nothing from a silent close.
- `{scheme}://gaps` lists what is open, blocking and most-reported first, with what was settled recently. The ground rules tell the assistant to read it before filing.
- The kit stores; the app routes. Listen for `GapReported` and open whatever your team actually reads — a task, an issue, a message. `GapStatusChanged` fires on a move.
- The distinction the ground rules insist on: a tool that refused because the token lacks an ability is **not** a gap — the app can do it and the fix is asking whoever grants it. Only a tool that does not exist is.
- New table `mcp_gap_reports`. Turn it off with `mcp-kit.gaps.enabled`.

## v1.2.1 — 2026-08-25

- A playbook may not take a built-in prompt's name: `getting_started` is refused at save time rather than leaving the client with two prompts of that name.

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
