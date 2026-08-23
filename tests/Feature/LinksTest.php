<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\Links;
use HeiHallo\McpKit\Links\RouteLinks;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers\AcmeServer;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Tools\ListThingsTool;
use HeiHallo\McpKit\Tests\Fixtures\Models\Thing;
use Illuminate\Support\Facades\Route;

test('without links the admin_url keys are omitted', function () {
    Thing::query()->create(['name' => 'Widget']);
    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)->tool(ListThingsTool::class, [])->assertOk()->assertDontSee('admin_url');
});

test('route links resolve models and filtered lists', function () {
    Route::get('/admin/things/{thing}', fn () => 'ok')->name('admin.things.show');
    Route::get('/admin/things', fn () => 'ok')->name('admin.things.index');
    config()->set('mcp-kit.links', RouteLinks::class);
    config()->set('mcp-kit.links_map', [
        'models' => [Thing::class => 'admin.things.show'],
        'lists' => ['things' => 'admin.things.index'],
    ]);
    app()->forgetInstance(Links::class);

    $thing = Thing::query()->create(['name' => 'Widget']);
    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)->tool(ListThingsTool::class, ['query' => 'Wid'])
        ->assertOk()
        ->assertSee('"admin_url":"http://localhost/admin/things/'.$thing->id.'"')
        ->assertSee('"list_admin_url":"http://localhost/admin/things?query=Wid"');

    expect(app(Links::class)->list('unknown'))->toBeNull();
});
