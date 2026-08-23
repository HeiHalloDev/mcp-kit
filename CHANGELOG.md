# Changelog

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
