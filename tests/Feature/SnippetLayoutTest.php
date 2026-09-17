<?php

declare(strict_types=1);

/**
 * The snippet partial is styled inline, and inline styles look like something
 * to tidy away into Tailwind classes. Two of these declarations are not
 * decoration: min-width keeps the block inside a grid or flex parent, and the
 * wrap makes a paragraph readable. Both were regressions once.
 */
test('a snippet cannot widen a grid or flex parent', function () {
    $rendered = view('mcp-kit::tokens.snippet', ['code' => str_repeat('x', 400)])->render();

    expect($rendered)->toContain('min-width: 0;');
});

test('a snippet scrolls sideways unless it is asked to wrap', function () {
    $plain = view('mcp-kit::tokens.snippet', ['code' => 'claude mcp add flex'])->render();
    $wrapped = view('mcp-kit::tokens.snippet', ['code' => 'A long sentence.', 'wrap' => true])->render();

    expect($plain)->not->toContain('white-space: pre-wrap')
        ->and($plain)->toContain('overflow-x: auto')
        ->and($wrapped)->toContain('white-space: pre-wrap')
        ->and($wrapped)->toContain('overflow-wrap: anywhere');
});
