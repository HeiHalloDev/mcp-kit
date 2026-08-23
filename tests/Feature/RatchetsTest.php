<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

function packageRoot(): string
{
    return dirname(__DIR__, 2);
}

/*
 * Arch ratchets. The package must never reference a consuming app's
 * namespace: app classes reach it through the config seams only.
 */
it('never references the App namespace in the shipped tree', function () {
    $root = packageRoot();

    $offenders = collect(['src', 'resources', 'config', 'routes', 'database', 'lang'])
        ->flatMap(fn (string $directory): array => File::allFiles($root.'/'.$directory))
        ->filter(fn (SplFileInfo $file): bool => (bool) preg_match('/(?<![A-Za-z\\\\])App\\\\/', $file->getContents()))
        ->map(fn (SplFileInfo $file): string => str_replace($root.'/', '', $file->getPathname()))
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

it('uses {{ namespace }} placeholders in stubs and never a literal App namespace', function () {
    $root = packageRoot();

    foreach (File::allFiles($root.'/stubs') as $stub) {
        expect($stub->getContents())->not->toMatch('/(?<![A-Za-z\\\\])App\\\\/', $stub->getFilename());
    }
});

/*
 * Public package: nothing client-specific. Brands, hostnames and the names
 * of client integrations stay out of the shipped tree.
 */
it('never carries a client brand', function () {
    $root = packageRoot();
    $brands = ['afpt', 'trustme', 'l5.no', 'helsekoden', 'bento', 'sveve', 'backbone', 'flex.afpt', 'io.afpt', 'heihallo-cms'];
    $offenders = [];

    foreach (['src', 'resources', 'config', 'routes', 'database', 'stubs', 'lang'] as $directory) {
        foreach (File::allFiles($root.'/'.$directory) as $file) {
            foreach ($brands as $brand) {
                if (stripos($file->getContents(), $brand) !== false) {
                    $offenders[] = str_replace($root.'/', '', $file->getPathname()).": {$brand}";
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('never ships ⚡-prefixed filenames', function () {
    $offenders = collect(File::allFiles(packageRoot().'/resources'))
        ->filter(fn (SplFileInfo $file): bool => str_contains($file->getFilename(), '⚡'))
        ->map(fn (SplFileInfo $file): string => $file->getFilename())
        ->all();

    expect($offenders)->toBe([]);
});

it('keeps Flux and spatie/laravel-permission out of require, and Sanctum and activitylog in', function () {
    $composer = json_decode((string) file_get_contents(packageRoot().'/composer.json'), true);

    expect($composer['require'])->toHaveKeys(['laravel/sanctum', 'spatie/laravel-activitylog', 'laravel/mcp'])
        ->not->toHaveKey('livewire/flux')
        ->not->toHaveKey('livewire/flux-pro')
        ->not->toHaveKey('spatie/laravel-permission')
        ->not->toHaveKey('livewire/livewire')
        ->and($composer['license'])->toBe('MIT');
});

it('has every view translation key in lang/en.json', function () {
    $root = packageRoot();
    $keys = json_decode((string) file_get_contents($root.'/lang/en.json'), true);
    $missing = [];

    $files = [...File::allFiles($root.'/resources/views'), ...File::allFiles($root.'/src/Livewire')];

    foreach ($files as $file) {
        preg_match_all("/__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $file->getContents(), $single);
        preg_match_all('/__\(\s*"((?:[^"\\\\]|\\\\.)*)"/', $file->getContents(), $double);

        foreach ([...$single[1], ...array_map(fn ($k) => stripslashes($k), $double[1])] as $key) {
            $key = str_replace("\\'", "'", $key);

            if (! array_key_exists($key, $keys)) {
                $missing[] = $key;
            }
        }
    }

    expect(array_values(array_unique($missing)))->toBe([]);
});
