<?php

declare(strict_types=1);

namespace HeiHallo\McpKit\Testing;

use Closure;
use HeiHallo\McpKit\Contracts\AbilityCatalogue;
use HeiHallo\McpKit\Contracts\PermissionChecker;
use HeiHallo\McpKit\Contracts\PresetResolver;
use HeiHallo\McpKit\Contracts\PrincipalResolver;
use HeiHallo\McpKit\Docs\ToolReference;
use HeiHallo\McpKit\Http\Middleware\EnsureMcpAccess;
use HeiHallo\McpKit\Servers\ServerRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Server\Registrar;

/**
 * The guard tests every app gets for one line in a test file:
 *
 *   Guards::all(inventory: __DIR__.'/tool-inventory.json');
 *
 * and Guards::actors(...) in tests/Pest.php. Each group can also be
 * registered on its own.
 */
final class Guards
{
    /**
     * The app's preset key with this grant kind, so the guards follow
     * read/support/admin as well as read/work/full.
     */
    public static function presetKeyOfGrant(string $grant): ?string
    {
        foreach ((array) config('mcp-kit.token_presets', []) as $key => $preset) {
            if (($preset['grant'] ?? 'grantable') === $grant) {
                return (string) $key;
            }
        }

        return null;
    }

    public static function readsPresetKey(): string
    {
        return self::presetKeyOfGrant('reads') ?? 'read';
    }

    public static function wildcardsPresetKey(): string
    {
        return self::presetKeyOfGrant('wildcards') ?? 'full';
    }

    /**
     * @param  (Closure(): Authenticatable)|null  $staff
     * @param  (Closure(): Authenticatable)|null  $privileged
     * @param  (Closure(): Authenticatable)|null  $blocked
     * @param  (Closure(): Authenticatable)|null  $serviceClient
     */
    public static function actors(?Closure $staff = null, ?Closure $privileged = null, ?Closure $blocked = null, ?Closure $serviceClient = null): void
    {
        Actors::register($staff, $privileged, $blocked, $serviceClient);
    }

    /**
     * @param  list<string>  $only
     * @param  list<string>  $except
     */
    public static function all(string $inventory, array $only = [], array $except = []): void
    {
        Expectations::register();

        $groups = [
            'abilities' => fn () => self::abilities(),
            'schemas' => fn () => self::schemas(),
            'access' => fn () => self::access(),
            'inventory' => fn () => self::inventory($inventory),
            'docsCurrent' => fn () => self::docsCurrent(),
            'presets' => fn () => self::presets(),
            'tokenCommand' => fn () => self::tokenCommand(),
            'groundRules' => fn () => self::groundRules(),
            'me' => fn () => self::me(),
        ];

        foreach ($groups as $name => $register) {
            if (($only !== [] && ! in_array($name, $only, true)) || in_array($name, $except, true)) {
                continue;
            }

            $register();
        }
    }

    /**
     * Every ability a tool checks is grantable, every referenced permission
     * exists, explicit-only abilities are never wildcard-granted, the
     * read-only set has no write, service-client writes are deliberate.
     */
    public static function abilities(): void
    {
        test('every ability a registered tool checks is grantable', function () {
            $reference = app(ToolReference::class);
            $catalogue = app(AbilityCatalogue::class);
            $missing = [];

            foreach ($reference->toolClasses() as $tool) {
                foreach ($reference->abilitiesFor($tool) as $ability) {
                    if (! $catalogue->exists($ability)) {
                        $missing[] = class_basename($tool).' => '.$ability;
                    }
                }
            }

            expect($missing)->toBe([], 'Tools check abilities no token can be granted: '.implode(', ', $missing));
        });

        test('every registered tool checks at least one ability', function () {
            $reference = app(ToolReference::class);
            $unguarded = [];

            foreach ($reference->toolClasses() as $tool) {
                if (str_starts_with($tool, 'HeiHallo\\McpKit\\')) {
                    continue; // the shared primitives gate on the person, not an ability
                }

                if ($reference->abilitiesFor($tool) === []) {
                    $unguarded[] = class_basename($tool);
                }
            }

            expect($unguarded)->toBe([], 'Tools without an ability check: '.implode(', ', $unguarded));
        });

        test('every permission the catalogue references is known', function () {
            $known = app(PermissionChecker::class)->knownPermissions();

            if ($known === []) {
                expect(true)->toBeTrue(); // the checker cannot enumerate permissions; nothing to compare against

                return;
            }

            $unknown = array_values(array_diff(app(AbilityCatalogue::class)->referencedPermissions(), $known));

            expect($unknown)->toBe([], 'The catalogue references unknown permissions: '.implode(', ', $unknown));
        });

        test('explicit-only abilities are never granted by a wildcard', function () {
            $catalogue = app(AbilityCatalogue::class);

            foreach (array_keys($catalogue->explicitOnly()) as $ability) {
                expect($catalogue->exists($ability))->toBeTrue("{$ability} is explicit-only but not in the catalogue.")
                    ->and($catalogue->isExplicitOnly($ability))->toBeTrue()
                    ->and($catalogue->wildcardsFor($ability))->toBe([])
                    ->and(array_key_exists($ability, $catalogue->all()))->toBeFalse("{$ability} must not be in the ordinary catalogue.")
                    ->and($catalogue->readOnly())->not->toContain($ability);
            }
        });

        test('the read-only set contains no write ability', function () {
            $catalogue = app(AbilityCatalogue::class);

            foreach ($catalogue->readOnly() as $ability) {
                expect($catalogue->isWrite($ability))->toBeFalse("{$ability} is a write.");
            }
        });

        test('service clients may read, and write only what the catalogue allows', function () {
            $catalogue = app(AbilityCatalogue::class);
            $allowed = array_map('strval', (array) config('mcp-kit.catalogue.service_client_writes', []));

            foreach ($catalogue->readOnly() as $ability) {
                expect($catalogue->allowedForServiceClient($ability))->toBeTrue();
            }

            foreach ($allowed as $ability) {
                expect($catalogue->exists($ability))->toBeTrue("{$ability} is listed as a service-client write but is unknown.")
                    ->and($catalogue->isWrite($ability))->toBeTrue("{$ability} is listed as a service-client write but is a read.");
            }

            foreach (array_keys($catalogue->all()) as $ability) {
                if ($catalogue->isWrite($ability) && ! in_array($ability, $allowed, true)) {
                    expect($catalogue->allowedForServiceClient($ability))->toBeFalse("{$ability} must be refused for service clients.");
                }
            }
        });

        test('legacy aliases resolve to known abilities', function () {
            $catalogue = app(AbilityCatalogue::class);

            expect((array) config('mcp-kit.catalogue.aliases', []))->toBeArray();

            foreach ((array) config('mcp-kit.catalogue.aliases', []) as $legacy => $canonical) {
                expect($catalogue->exists((string) $canonical))->toBeTrue("Alias {$legacy} points at unknown ability {$canonical}.")
                    ->and($catalogue->canonical((string) $legacy))->toBe((string) $canonical);
            }
        });
    }

    /**
     * laravel/mcp builds the advertised schema from schema(JsonSchema) and
     * ignores a $inputSchema property, so without the DeclaresInputSchema
     * bridge a tool ships {"type":"object","properties":{}} and clients
     * guess arguments from prose. These make that silence loud.
     */
    public static function schemas(): void
    {
        $schemas = static function (): array {
            $schemas = [];

            foreach (app(ToolReference::class)->toolClasses() as $class) {
                $payload = app($class)->toArray();
                $schemas[$payload['name']] = json_decode((string) json_encode($payload['inputSchema']), true);
            }

            return $schemas;
        };

        test('every tool advertises an object schema', function () use ($schemas) {
            foreach ($schemas() as $name => $schema) {
                expect($schema['type'] ?? null)->toBe('object', "[{$name}] has no object schema.")
                    ->and($schema)->toHaveKey('properties');
            }
        });

        test('a tool without parameters advertises properties as an object, never an empty array', function () {
            foreach (app(ToolReference::class)->toolClasses() as $class) {
                $json = (string) json_encode(app($class)->toArray()['inputSchema']);

                expect(str_contains($json, '"properties":{'))->toBeTrue(class_basename($class).' serialises properties as [] — clients drop the whole server over that schema.');
            }
        });

        test('every advertised property is typed', function () use ($schemas) {
            foreach ($schemas() as $name => $schema) {
                foreach ((array) ($schema['properties'] ?? []) as $property => $definition) {
                    $definition = (array) $definition;

                    expect(array_key_exists('type', $definition) || array_key_exists('anyOf', $definition) || array_key_exists('oneOf', $definition) || array_key_exists('$ref', $definition))
                        ->toBeTrue("[{$name}.{$property}] has no type.");
                }
            }
        });

        test('required parameters exist in the schema', function () use ($schemas) {
            foreach ($schemas() as $name => $schema) {
                $properties = array_keys((array) ($schema['properties'] ?? []));

                foreach ((array) ($schema['required'] ?? []) as $required) {
                    expect(in_array($required, $properties, true))->toBeTrue("[{$name}] marks [{$required}] required but never declares it.");
                }
            }
        });

        test('tools with a confirm flag never require it so the bare call previews', function () use ($schemas) {
            foreach ($schemas() as $name => $schema) {
                if (! array_key_exists('confirm', (array) ($schema['properties'] ?? []))) {
                    continue;
                }

                expect((array) ($schema['required'] ?? []))->not->toContain('confirm', "[{$name}] must not require confirm — the bare call is the preview.");
            }
        });
    }

    /**
     * Transport-level access with real bearer tokens, and the route shape
     * that keeps a browser session out.
     */
    public static function access(): void
    {
        test('every kit server route carries the access guard and never session middleware', function () {
            $servers = app(ServerRegistry::class)->all();

            expect($servers)->not->toBe([], 'No servers under mcp-kit.servers.');

            foreach ($servers as $definition) {
                $route = collect(Route::getRoutes()->getRoutes())
                    ->first(fn ($route): bool => $route->uri() === $definition->uri() && in_array('POST', $route->methods(), true));

                expect($route)->not->toBeNull("{$definition->uri()} is not registered.");

                $middleware = $route->gatherMiddleware();

                expect($middleware)->not->toContain('web')
                    ->and($middleware)->not->toContain(StartSession::class)
                    ->and($middleware)->toContain(EnsureMcpAccess::class)
                    ->and($middleware)->toContain('auth:sanctum');
            }
        });

        test('every MCP web route is registered through the kit', function () {
            $allowed = array_map(static fn ($uri): string => ltrim((string) $uri, '/'), (array) config('mcp-kit.routes.allow_unguarded', []));

            foreach (app(Registrar::class)->servers() as $route) {
                if (! $route instanceof \Illuminate\Routing\Route || in_array(ltrim($route->uri(), '/'), $allowed, true)) {
                    continue;
                }

                expect((string) $route->getName())->toStartWith('mcp-kit.', "{$route->uri()} bypasses McpKit::server().");
            }
        });

        test('an unauthenticated request is rejected', function () {
            foreach (app(ServerRegistry::class)->all() as $definition) {
                Mcp::listTools(null, $definition->path)->assertUnauthorized();
            }
        });

        test('a token without any catalogue ability is rejected', function () {
            if (! Actors::has('staff')) {
                test()->markTestSkipped('No staff actor registered (Guards::actors).');
            }

            $definition = array_values(app(ServerRegistry::class)->all())[0];
            $token = Mcp::token(Actors::make('staff'), ['something-else']);

            Mcp::listTools($token, $definition->path)->assertForbidden();
        });

        test('a staff token reaches the servers its abilities cover and no other', function () {
            if (! Actors::has('staff')) {
                test()->markTestSkipped('No staff actor registered (Guards::actors).');
            }

            $catalogue = app(AbilityCatalogue::class);
            $presets = app(PresetResolver::class);
            $user = Actors::make('staff');
            $abilities = $presets->abilitiesFor($user, Guards::readsPresetKey());

            if ($abilities === []) {
                test()->markTestSkipped('The read preset resolves to nothing for the staff actor — give it a permission.');
            }

            $token = Mcp::token($user, $abilities);
            $reached = $catalogue->serversFor($abilities);

            foreach (app(ServerRegistry::class)->all() as $key => $definition) {
                $response = Mcp::listTools($token, $definition->path);

                $allowed = in_array($key, $reached, true) && (! $definition->requiresStaff || app(PermissionChecker::class)->isStaff($user));

                if ($allowed) {
                    $response->assertSuccessful();
                } else {
                    $response->assertForbidden();
                }
            }
        });

        test('a blocked owner is rejected even with a wildcard', function () {
            if (! Actors::has('blocked')) {
                test()->markTestSkipped('No blocked actor registered (Guards::actors).');
            }

            $definition = array_values(app(ServerRegistry::class)->all())[0];
            $token = Mcp::token(Actors::make('blocked'), $definition->wildcard !== null ? [$definition->wildcard] : ['*']);

            Mcp::listTools($token, $definition->path)->assertForbidden();
        });

        test('a privileged owner with the wildcards preset reaches every server in the presets', function () {
            if (! Actors::has('privileged')) {
                test()->markTestSkipped('No privileged actor registered (Guards::actors).');
            }

            $user = Actors::make('privileged');
            $abilities = app(PresetResolver::class)->abilitiesFor($user, Guards::wildcardsPresetKey());

            expect($abilities)->not->toBe([], 'The full preset resolves to nothing for the privileged actor.');

            $token = Mcp::token($user, $abilities);
            $reached = app(AbilityCatalogue::class)->serversFor($abilities);

            foreach (app(ServerRegistry::class)->all() as $key => $definition) {
                $response = Mcp::listTools($token, $definition->path);

                // A server that opted out of presets (customer-facing) is not
                // in the full preset and must turn the token away.
                if (in_array($key, $reached, true)) {
                    $response->assertSuccessful();
                } else {
                    expect($definition->presets)->toBeFalse("The full preset does not reach {$key}.");
                    $response->assertForbidden();
                }
            }
        });

        test('an active service client reaches the servers that accept it', function () {
            if (! Actors::has('serviceClient')) {
                test()->markTestSkipped('No service-client actor registered (Guards::actors).');
            }

            $catalogue = app(AbilityCatalogue::class);
            $abilities = $catalogue->readOnly();

            if ($abilities === []) {
                test()->markTestSkipped('No read abilities in the catalogue.');
            }

            $token = Mcp::token(Actors::make('serviceClient'), $abilities, 'service');
            $reached = $catalogue->serversFor($abilities);

            foreach (app(ServerRegistry::class)->all() as $key => $definition) {
                $response = Mcp::listTools($token, $definition->path);

                if ($definition->serviceClients && in_array($key, $reached, true)) {
                    $response->assertSuccessful();
                } else {
                    $response->assertForbidden();
                }
            }
        });
    }

    /**
     * Tool names are a contract: clients cache the catalogue, and a tool that
     * disappears or is renamed looks like an outage. Pins the inventory.
     */
    public static function inventory(string $path): void
    {
        test('the registered tool inventory matches the pinned snapshot', function () use ($path) {
            expect(is_file($path))->toBeTrue("Missing {$path} — run php artisan mcp:install.");

            $pinned = json_decode((string) file_get_contents($path), true);
            $actual = app(ToolReference::class)->inventory();

            foreach ((array) $pinned as $server => $names) {
                $missing = array_values(array_diff((array) $names, $actual[$server] ?? []));

                expect($missing)->toBe([], "[{$server}] lost tools: ".implode(', ', $missing).". Removing or renaming a tool breaks connected clients — if deliberate, update {$path}.");
            }

            expect($actual)->toBe($pinned, "New tools registered — regenerate {$path} (php artisan mcp:install --force keeps the rest) so the snapshot stays deliberate.");
        });

        test('tool names are unique within a server', function () {
            $reference = app(ToolReference::class);

            foreach (app(ServerRegistry::class)->all() as $key => $definition) {
                $names = array_map(fn (string $class): string => $reference->toolName($class), $reference->toolClassesFor($definition->class));

                expect(array_values(array_unique($names)))->toBe(array_values($names), "[{$key}] registers a tool name twice.");
            }
        });
    }

    public static function docsCurrent(): void
    {
        test('the generated tool reference is current', function () {
            test()->artisan('mcp:docs', ['--check' => true])->assertSuccessful();
        });

        test('every registered tool appears in the reference', function () {
            $reference = app(ToolReference::class);
            $document = (string) file_get_contents($reference->path());

            foreach ($reference->tools() as $tool) {
                expect($document)->toContain('`'.$tool['name'].'`');
            }
        });
    }

    public static function presets(): void
    {
        test('the reads preset grants no write and the wildcards preset follows the privilege rule', function () {
            $presets = app(PresetResolver::class);

            if (Actors::has('staff')) {
                $staff = Actors::make('staff');

                expect($presets->grantsWrite($presets->abilitiesFor($staff, Guards::readsPresetKey())))->toBeFalse();

                $staffIsPrivileged = (bool) app(PrincipalResolver::class)->resolve($staff)?->privileged;

                if (config('mcp-kit.tokens.wildcards_require_privileged', true) && ! $staffIsPrivileged) {
                    expect($presets->abilitiesFor($staff, Guards::wildcardsPresetKey()))->toBe([]);
                }
            }

            if (Actors::has('privileged')) {
                $privileged = Actors::make('privileged');
                $full = $presets->abilitiesFor($privileged, Guards::wildcardsPresetKey());

                expect($full)->not->toBe([])
                    ->and($presets->grantsWrite($full))->toBeTrue()
                    ->and($presets->grantsExplicitOnly($full))->toBeFalse('The full preset must not include explicit-only abilities.');
            }

            expect(true)->toBeTrue();
        });

        test('preset labels round-trip from the abilities they grant', function () {
            if (! Actors::has('privileged')) {
                test()->markTestSkipped('No privileged actor registered (Guards::actors).');
            }

            $presets = app(PresetResolver::class);
            $user = Actors::make('privileged');

            foreach ($presets->availableFor($user) as $key => $preset) {
                if (! in_array($key, ['read', 'full'], true) && ! is_array(config("mcp-kit.token_presets.{$key}.grant"))) {
                    continue;
                }

                expect($presets->labelForAbilities($preset['abilities']))->toBe($preset['label'], "Preset {$key} does not label back to itself.");
            }
        });
    }

    public static function tokenCommand(): void
    {
        test('mcp:token mints a token and prints one connect line per server reached', function () {
            if (! Actors::has('staff')) {
                test()->markTestSkipped('No staff actor registered (Guards::actors).');
            }

            $user = Actors::make('staff');
            $abilities = app(PresetResolver::class)->abilitiesFor($user, Guards::readsPresetKey());

            if ($abilities === []) {
                test()->markTestSkipped('The read preset resolves to nothing for the staff actor.');
            }

            $command = test()->artisan('mcp:token', ['email' => $user->email, '--preset' => Guards::readsPresetKey(), '--name' => 'guard-test'])
                ->assertSuccessful();

            foreach (app(AbilityCatalogue::class)->serversFor($abilities) as $key) {
                $command->expectsOutputToContain('claude mcp add '.app(ServerRegistry::class)->get($key)->clientName);
            }

            $command->run();

            expect($user->tokens()->where('name', 'like', '%guard-test')->exists())->toBeTrue();

            test()->artisan('mcp:token', ['email' => $user->email, '--revoke' => true])->assertSuccessful();

            expect($user->fresh()->tokens()->where('name', 'like', '%guard-test')->exists())->toBeFalse();
        });

        test('mcp:token refuses an unknown person', function () {
            test()->artisan('mcp:token', ['email' => 'nobody-'.uniqid().'@example.test'])->assertFailed();
        });
    }

    public static function groundRules(): void
    {
        test('the ground rules are listed and readable on every server', function () {
            if (! Actors::has('staff')) {
                test()->markTestSkipped('No staff actor registered (Guards::actors).');
            }

            $scheme = config('mcp-kit.scheme');
            $user = Actors::make('staff');
            $abilities = app(PresetResolver::class)->abilitiesFor($user, Guards::readsPresetKey());

            if ($abilities === []) {
                test()->markTestSkipped('The read preset resolves to nothing for the staff actor.');
            }

            $token = Mcp::token($user, $abilities);

            foreach (app(AbilityCatalogue::class)->serversFor($abilities) as $key) {
                $definition = app(ServerRegistry::class)->get($key);

                if ($definition->requiresStaff && ! app(PermissionChecker::class)->isStaff($user)) {
                    continue;
                }

                expect(Mcp::rpc($token, $definition->path, 'resources/list')->assertSuccessful()->json('result.resources.*.uri'))->toContain("{$scheme}://ground-rules");

                $read = Mcp::readResource($token, $definition->path, "{$scheme}://ground-rules")->assertSuccessful();

                if (! in_array('names_and_links', (array) config('mcp-kit.ground_rules.remove', []), true)) {
                    $read->assertSee('Names and links');
                }
            }
        });
    }

    public static function me(): void
    {
        test('the me resource is listed and readable and names the person', function () {
            if (! Actors::has('staff')) {
                test()->markTestSkipped('No staff actor registered (Guards::actors).');
            }

            $scheme = config('mcp-kit.scheme');
            $user = Actors::make('staff');
            $abilities = app(PresetResolver::class)->abilitiesFor($user, Guards::readsPresetKey());

            if ($abilities === []) {
                test()->markTestSkipped('The read preset resolves to nothing for the staff actor.');
            }

            $token = Mcp::token($user, $abilities);

            foreach (app(AbilityCatalogue::class)->serversFor($abilities) as $key) {
                $definition = app(ServerRegistry::class)->get($key);

                if ($definition->requiresStaff && ! app(PermissionChecker::class)->isStaff($user)) {
                    continue;
                }

                expect(Mcp::rpc($token, $definition->path, 'resources/list')->assertSuccessful()->json('result.resources.*.uri'))->toContain("{$scheme}://me");
                Mcp::readResource($token, $definition->path, "{$scheme}://me")->assertSuccessful()->assertSee(e(explode(' ', (string) $user->name)[0]));
            }
        });
    }
}
