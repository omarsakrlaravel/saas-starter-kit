<?php

use Illuminate\Support\Facades\File;

test('blade views do not use native wire confirm dialogs', function () {
    $bladeDirectories = [
        resource_path(),
        base_path('wave/resources/views'),
    ];

    $filesWithNativeConfirmDialogs = collect($bladeDirectories)
        ->flatMap(fn (string $directory) => File::allFiles($directory))
        ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
        ->filter(fn ($file) => str_contains($file->getContents(), 'wire:confirm'))
        ->map(fn ($file) => str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()))
        ->values();

    expect($filesWithNativeConfirmDialogs)->toBeEmpty();
});
