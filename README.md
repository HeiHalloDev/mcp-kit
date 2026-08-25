# heihallo/mcp-kit

Staff tooling over MCP for Laravel apps, as a package: the ability catalogue, per-person tokens, access and audit middleware, preview/confirm writes, ground rules, a `me` resource with per-user assistant memory, saved playbooks, gap reports, and a short optional onboarding. Every app that exposes tools to Claude, Codex or another assistant needs the same foundation; this is it, once.

Requires PHP 8.3+, Laravel 12 or 13, [laravel/mcp](https://github.com/laravel/mcp) 0.9, [Sanctum](https://laravel.com/docs/sanctum) 4 and [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog) 4.9 or 5. Postgres first; other databases work for everything but the JSONB memory column.

## What you get

- **One catalogue** of token abilities (`'shop:orders:read' => ['Search orders', 'orders', 'shop']`) that the tools, the token command, the tokens page and the tests all read. A token never out-ranks its owner: the permission behind each ability is re-checked on every call.
- **Guarded servers.** `mcp-kit.servers` registers each server with `auth:sanctum`, a per-token throttle, `EnsureMcpAccess` (blocked owners, inactive service clients, browser sessions, tokens without an ability for this server) and `AuditMcpCall`. An `Mcp::web` route that bypasses the kit makes the app refuse to boot.
- **One write shape.** `previewOrExecute()` previews without `confirm=true` and executes with it. Every confirmed write and every tool call becomes an `activity_log` row in the `mcp` log, in the person's name, with the sanitised arguments and a `call_id` shared by the domain rows written during the call.
- **`{scheme}://me`** tells the assistant who it is talking to: role, team, what this token may do, how the person usually works, what was remembered. A hint, not a mode.
- **`remember_about_me`** saves what the person confirms, in a JSON column on `users`. **`getting_started`** asks only for what the app does not already know, at most three questions, and is offered once.
- **Gap reports.** `report_gap` files what someone needed and the app could not do — deduplicated, so the same gap gathers weight rather than duplicating, and routed by your own listener on `GapReported`. `{scheme}://gaps` lists what is open.
- **Playbooks.** `save_playbook` keeps a way of working the person wants back, and every one they saved is offered as an MCP prompt — a slash command in Claude Code. Scope one to certain servers or abilities; privileged staff may share one with everybody. `{scheme}://playbooks` lists them.
- **Commands**: `mcp:install`, `mcp:token`, `mcp:client-token`, `mcp:docs`, `mcp:audit-tokens`, `mcp-kit:prune`.
- **Tests for free.** `Guards::all()` gives an app the ability, schema, access, inventory, docs, preset, token-command, ground-rules and `me` checks in one line.

## Install

```bash
composer require heihallo/mcp-kit
php artisan mcp:install
php artisan migrate
```

`mcp:install` publishes `config/mcp-kit.php`, writes a server class under `app/Mcp/Servers`, a ground-rules intro view, `tests/Feature/Mcp/KitGuardsTest.php` with its inventory snapshot, and the docs page. It is safe to run again. Add `--scan` to have it read the abilities your existing tools already check and add catalogue entries for them.

Then, in `config/mcp-kit.php`:

```php
'scheme' => 'shop',

'servers' => [
    'shop' => [
        'class' => App\Mcp\Servers\ShopServer::class,
        'path' => '/mcp/shop',
        'label' => 'Shop',
        'wildcard' => 'shop:*',
        'client_name' => 'shop',
        'requires_staff' => true,
    ],
],

'catalogue' => [
    'abilities' => [
        'shop:orders:read' => ['Search and view orders', 'orders', 'shop'],
        'shop:orders:write' => ['Change order status, refund', 'orders', 'shop'],
        'shop:customers:read' => ['Search customers', 'customers', 'shop'],
    ],
    'explicit_only' => [],
    'service_client_writes' => [],
],

'permission_rules' => [
    'staff_permission' => 'access admin',
    'privileged_roles' => ['Owner', 'Dev'],
],
```

A tool:

```php
use HeiHallo\McpKit\Tools\StaffTool;

#[IsReadOnly]
class SearchOrdersTool extends StaffTool
{
    protected string $name = 'search_orders';
    protected string $description = 'Search orders by number, customer or status.';
    protected array $inputSchema = ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]];

    public function handle(Request $request): Response
    {
        if ($denied = $this->requireAbility($request, 'shop:orders:read')) {
            return $denied;
        }

        return Response::json(['orders' => Order::search($request->get('query'))->map(
            fn (Order $order) => $this->withAdminUrl(['number' => $order->number, 'status' => $order->status], $order),
        )]);
    }
}
```

A write:

```php
return $this->previewOrExecute(
    $request,
    ['order' => $order->number, 'from' => $order->status, 'to' => $status],
    fn () => ['order' => $order->refresh()->only('number', 'status')],
    'Change order status',
    ['subject' => $order],
);
```

Mint a token and connect:

```bash
php artisan mcp:token kari@example.com
```

It prints the token and one `claude mcp add …` line per server the token reaches.

## Overriding behaviour

Every replaceable piece is a contract bound from a config key. Point the key at your own class, or re-bind the contract in your provider. Never edit a package file.

| Contract | Default | Config key | Swap it when |
|---|---|---|---|
| `PrincipalResolver` | `DefaultPrincipalResolver` | `principal` | "blocked" means something else (`McpKit::blockedUsing()` for the simple case) |
| `PermissionChecker` | `GatePermissionChecker`; also `SpatiePermissionChecker`, `ModelPermissionChecker` | `permissions`, `permission_rules` | permissions live in spatie, or on your user model |
| `ServiceClient` (model) | `Models\ServiceClient` | `models.service_client` | you already have an API-client model; `null` disables service clients |
| `Links` | `NullLinks`; also `RouteLinks` | `links`, `links_map` | tool results should carry admin page links |
| `UserDescriber` | `AutoDescriber` | `describer`, `describer_options` | `me` should mention inboxes, shifts, anything app-specific |
| `GroundRules` | `SectionedGroundRules` | `ground_rules.{class,intro,sections,remove}` | add sections, drop one, or render from a database |
| `AuditWriter` | `ActivityLogAuditWriter`; also `NullAuditWriter` | `audit`, `activity` | calls should also go somewhere else |
| `ResolvesActivitySource` | `NullSourceResolver` | `activity.source_resolver` (`McpKit::resolveSourceUsing()`) | activity rows should name the product |
| `AbilityCatalogue` | `ConfigAbilityCatalogue` | `abilities`, `catalogue` | you prefer PHP constants |
| `PresetResolver` | `ConfigPresetResolver` | `presets`, `token_presets` | other presets (`'analyst' => ['grant' => ['reports:read']]`) |
| `TokenPolicy` | `DefaultTokenPolicy` | `token_policy`, `tokens` | a different prefix or expiry |
| `MemoryStore`, `MemoryPolicy` | `ColumnMemoryStore`, `DefaultMemoryPolicy` | `memory` | team leads may view their team's memory |
| `OnboardingQuestions`, `SuggestsTasks` | `DefaultQuestions`, `ConfigSuggestions` | `onboarding`, `suggestions` | your own questions, suggestions per ability |

Views: `php artisan vendor:publish --tag=mcp-kit-views` and edit what you need under `resources/views/vendor/mcp-kit`. A single partial (the ground-rules intro, the tokens-page examples) can be overridden on its own.

Events at every hook point: `TokenMinted`, `TokenRevoked`, `AccessDenied`, `AbilityDenied`, `ToolCallRecorded`, `WritePreviewed`, `WriteConfirmed`, `MemoryUpdated`, `OnboardingOffered`, `OnboardingCompleted`, `OnboardingDeclined`.

## Activity log

The kit standardises on spatie/laravel-activitylog and adds three columns to its table: `source` (the product), `channel` (web, mcp, api, cli, chat, system) and `token_name`. They are filled on every row as it is created, only where still null, whatever model the app uses. Tool calls land in the `mcp` log: `event` is read, previewed, executed, denied or failed; `description` is the confirmed action or the tool name; `properties` carry tool, server, arguments, result, duration, `call_id`, client. `mcp-kit:prune` removes rows older than `activity.retain_days`; schedule it daily.

## Optional UI

With Livewire 4 and Flux installed, set `mcp-kit.ui.enabled` and `ui.tokens_page.enabled` (or run `mcp:install --with-tokens-page`). The page at `settings/tokens` mints tokens with a preset filtered by the person's own permissions, lists and revokes them, shows the connect snippets for Claude Code, Claude Desktop, Codex and cURL, and ends with what the assistant remembers about the person. The table and tabs are Flux Pro components. Add the package views to Tailwind: `@source '../../vendor/heihallo/mcp-kit/resources/views';`.

## Testing in your app

```php
// tests/Feature/Mcp/KitGuardsTest.php
Guards::all(inventory: __DIR__.'/tool-inventory.json');

// tests/Pest.php
Guards::actors(
    staff: fn () => User::factory()->create(),
    privileged: fn () => User::factory()->owner()->create(),
    blocked: fn () => User::factory()->blocked()->create(),
    serviceClient: fn () => ServiceClient::create(['name' => 'harness', 'slug' => 'harness']),
);
```

Helpers: `Testing\Mcp::token()`, `::actingWith()`, `::listTools()`, `::call()`, `::readResource()`; expectations `toBePreview()`, `toHaveExecuted()`, `toDenyAbility()`.

## Read-only mode

`MCP_READ_ONLY=true` hides every tool not annotated `#[IsReadOnly]` and makes `previewOrExecute()` refuse `confirm=true`.

## Developing the package

```bash
createdb mcp_kit_testbench
composer install
vendor/bin/pest
```

The suite wipes its database on every run and refuses to start against one not named `mcp_kit_testbench*`.

## License

MIT.
