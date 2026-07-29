<?php

use Illuminate\Support\Facades\File;

test('Blade views only reference existing minified local CSS and JavaScript assets', function () {
    $assetReferences = collect(File::allFiles(resource_path('views')))
        ->flatMap(function ($file): array {
            preg_match_all(
                '/\basset\(\s*[\'"]([^\'"]+\.(?:css|js))[\'"]\s*\)/',
                File::get($file->getRealPath()),
                $matches,
            );

            return $matches[1];
        })
        ->unique()
        ->values();

    $unminifiedAssets = $assetReferences
        ->reject(fn (string $asset): bool => str_ends_with($asset, '.min.css') || str_ends_with($asset, '.min.js'))
        ->values();

    $missingAssets = $assetReferences
        ->reject(fn (string $asset): bool => File::exists(public_path($asset)))
        ->values();

    expect($unminifiedAssets->all())->toBe([])
        ->and($missingAssets->all())->toBe([]);
});
