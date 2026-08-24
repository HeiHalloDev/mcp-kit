---
nav_title: Tools
---

<!-- generated:tools:start -->

## Tool summary (generated)

5 tools across 2 servers. 2 read, 3 write. Every tool checks a token ability (see `config/mcp-kit.php`) before executing.

| # | Tool | Server | Domain | Ability | Type |
|---|------|--------|--------|---------|------|
| 1 | `admin_only` | acme | General | `acme:admin` | **Write** |
| 2 | `list_things` | acme | General | `acme:things:read` | Read |
| 3 | `log_event` | acme | General | `acme:events:write` | **Write** |
| 4 | `update_thing` | acme | General | `acme:things:write` | **Write** |
| 5 | `monthly_numbers` | reports | General | `reports:read` | Read |

## Write tools (generated)

These modify data. Unless noted, each previews without `confirm=true` and executes with it. Service-client tokens are refused on every write except those behind `acme:events:write`.

| Tool | Server | Ability | Annotations | What it does |
|------|--------|---------|-------------|--------------|
| `admin_only` | acme | `acme:admin` | — | Changes what other people may do. |
| `log_event` | acme | `acme:events:write` | IsIdempotent | System logging from another service: a write service clients may perform. |
| `update_thing` | acme | `acme:things:write` | IsIdempotent | Rename a thing. |
<!-- generated:tools:end -->
