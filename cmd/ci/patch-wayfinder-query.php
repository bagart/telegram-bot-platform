<?php

declare(strict_types=1);

/**
 * Bridge for an upstream wayfinder/Laravel 13 type mismatch.
 *
 * Laravel 13 added the HTTP QUERY verb (Router::$verbs), so `Route::any`
 * routes generate `methods: [..., "query"]` action definitions. Wayfinder
 * v0.1.21 still ships a TS runtime (resources/js/wayfinder/index.ts, copied
 * from its vendor resources) whose `Method` union lacks `"query"`, making
 * `tsc --noEmit` fail with TS2561/TS2344 on every generated ANY/QUERY
 * action. This script idempotently appends `"query"` to that generated
 * union; it no-ops once upstream ships the verb.
 *
 * Wired into package.json `build`/`build:ssr`/`types:check` because the
 * vite wayfinder plugin regenerates the file on every build.
 *
 * Usage: php cmd/ci/patch-wayfinder-query.php
 */

$root = dirname(__DIR__, 2);
$target = $root . '/resources/js/wayfinder/index.ts';

if (!is_file($target)) {
    // Generated tree absent (fresh checkout before the first build) — nothing
    // to patch; tsc would fail on missing files anyway.
    exit(0);
}

$source = (string) file_get_contents($target);
$patched = preg_replace_callback(
    '#type Method = ([^;]+);#',
    static fn (array $matches): string => str_contains($matches[1], '"query"')
        ? $matches[0]
        : 'type Method = ' . $matches[1] . ' | "query";',
    $source,
    1,
);
if ($patched === null) {
    fwrite(STDERR, "patch-wayfinder-query: regex failed on {$target}\n");
    exit(1);
}

if ($patched !== $source) {
    file_put_contents($target, $patched);
    echo "patch-wayfinder-query: added \"query\" to Method union\n";
}

exit(0);
