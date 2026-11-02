<?php

test('feature tests live in module directories wired to module groups', function (): void {
    $modules = ['account', 'dashboard', 'settings', 'statistics', 'tag', 'template', 'transaction', 'transfer'];
    $base = __DIR__ . '/../Feature/App';
    $pest = (string) file_get_contents(__DIR__ . '/../Pest.php');

    $dirs = array_map(
        fn (string $path): string => strtolower(basename($path)),
        glob($base . '/*', GLOB_ONLYDIR) ?: [],
    );
    sort($dirs);

    // Every App test file sits inside a module directory, every module
    // directory is a known one, and Pest.php maps each to a module: group —
    // so --group=module:<name> always selects a feature's full relevance.
    expect($dirs)->toBe($modules)
        ->and(glob($base . '/*Test.php'))->toBeEmpty();

    foreach ($modules as $module) {
        expect($pest)->toContain("'module:{$module}'");
    }
});
