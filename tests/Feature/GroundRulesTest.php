<?php

declare(strict_types=1);

use HeiHallo\McpKit\Contracts\GroundRules;
use HeiHallo\McpKit\Contracts\Section;
use HeiHallo\McpKit\Mcp\Resources\GroundRulesResource;
use HeiHallo\McpKit\Principal;
use HeiHallo\McpKit\Tests\Fixtures\Mcp\Servers\AcmeServer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

test('the default order is intro, safety, tokens, names and links, replies, memory, playbooks, gaps', function () {
    $sections = app(GroundRules::class)->sections(null, 'acme');

    expect(array_keys($sections))->toBe(['What this is', 'Safety', 'People and tokens', 'Names and links', 'Replies', 'The person you are helping', 'Playbooks', 'What this app cannot do'])
        ->and($sections['What this is'])->toContain('What this app is: Acme.')
        ->and($sections['What this is'])->toContain('**Acme** (`acme`): Things and events.')
        ->and($sections['People and tokens'])->toContain('`acme:admin`')
        ->and($sections['The person you are helping'])->toContain('acme://me')
        ->and($sections['Names and links'])->toContain('admin_url');
});

test('the app adds sections in three shapes, removes package ones and sets the intro', function () {
    View::addNamespace('harness', __DIR__.'/../resources/views');
    config()->set('mcp-kit.ground_rules.intro', 'Acme sells widgets.');
    config()->set('mcp-kit.ground_rules.remove', ['replies']);
    config()->set('mcp-kit.ground_rules.sections', [
        'Money' => 'Amounts are whole kroner.',
        'Language' => 'view:harness::language',
        'Computed' => new class implements Section
        {
            public function render(?Principal $principal, ?string $server): string
            {
                return 'Server: '.($server ?? 'none');
            }
        },
    ]);
    Cache::flush();

    $text = app(GroundRules::class)->text(null, 'acme');

    expect($text)->toStartWith('# Acme — ground rules for staff tools')
        ->toContain('Acme sells widgets.')
        ->toContain("## Money\nAmounts are whole kroner.")
        ->toContain("## Language\nContent is Norwegian.")
        ->toContain("## Computed\nServer: acme")
        ->not->toContain('## Replies');
});

test('a package partial can be overridden as a vendor view', function () {
    View::replaceNamespace('mcp-kit', [__DIR__.'/../resources/views/vendor/mcp-kit', dirname(__DIR__, 2).'/resources/views']);
    Cache::flush();

    expect(app(GroundRules::class)->sections(null, null)['Safety'])->toBe('Overridden safety.');
});

test('the class can be replaced and the resource serves it', function () {
    // Assigned first rather than `new class {}::class`, which only parses on
    // PHP 8.4 and broke the 8.3 leg of the matrix.
    $rules = new class implements GroundRules
    {
        public function sections(?Principal $principal, ?string $server): array
        {
            return ['Only' => 'one rule'];
        }

        public function text(?Principal $principal, ?string $server): string
        {
            return "# Custom\n\none rule";
        }

        public function instructions(string $server, string $authored): string
        {
            return $authored.' [custom]';
        }
    };

    config()->set('mcp-kit.ground_rules.class', $rules::class);
    app()->forgetInstance(GroundRules::class);

    $user = actingWith(acmeUser(), ['acme:things:read']);

    AcmeServer::actingAs($user)->resource(GroundRulesResource::class)->assertOk()->assertSee('# Custom');
});

test('the resource is at {scheme}://ground-rules with a markdown mime type', function () {
    $resource = app(GroundRulesResource::class);

    expect($resource->uri())->toBe('acme://ground-rules')
        ->and($resource->mimeType())->toBe('text/markdown')
        ->and($resource->description())->toContain('Acme');
});
