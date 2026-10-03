<?php

declare(strict_types=1);

/**
 * Rewrite in-tree misc/BAGArt references to the vendor layout produced by
 * `cmd/deps/install --mode=prod --dev` (decision Q12-A). ask-queue maps like
 * every other package since Q17-A published it (2026-10-01).
 * Re-dumps the autoloader afterwards.
 *
 * Ephemeral CI checkouts only: in dev mode vendor/bagart/* are symlinks into
 * misc/, so running this locally would duplicate every suite.
 *
 * Usage: php cmd/ci/rewrite-vendor-paths.php
 */

const EX_OK = 0;
const EX_CHECK = 1;
const EX_USAGE = 2;

$root = dirname(__DIR__, 2);
chdir($root);

/** @return never */
function fail(string $message, int $code = EX_CHECK): never
{
    fwrite(STDERR, "rewrite-vendor-paths: {$message}\n");
    exit($code);
}

if (is_link('vendor/bagart/async-kernel') || is_dir('misc/BAGArt/telegram-bot-lib/src')) {
    fail('dev checkout detected (symlinked/misc sources) — CI-only tool', EX_USAGE);
}

/** dir (misc/BAGArt/<dir>) => composer package (vendor/<pkg>). */
$map = [
    'ask-queue' => 'bagart/ask-queue',
    'php-async-kernel-client-redis' => 'bagart/ask-client-redis',
    'php-async-kernel-client' => 'bagart/ask-client',
    'php-async-kernel-lib' => 'bagart/async-kernel',
    'telegram-bot-lib-basic' => 'bagart/telegram-bot-lib-basic',
    'telegram-bot-lib' => 'bagart/telegram-bot-lib',
    'telegram-platform-access' => 'bagart/telegram-platform-access',
    'telegram-platform-audit' => 'bagart/telegram-platform-audit',
    'telegram-platform-devops-baseline' => 'bagart/telegram-platform-devops-baseline',
    'telegram-platform-management' => 'bagart/telegram-platform-management',
    'telegram-platform-menu' => 'bagart/telegram-platform-menu',
    'telegram-platform-module' => 'bagart/telegram-platform-module',
    'tgbot-game-mafia' => 'bagart/tgbot-game-mafia',
    'tgbot-module-antispam' => 'bagart/tgbot-module-antispam',
    'tgbot-module-example' => 'bagart/tgbot-module-example',
    'tgbot-module-nettools' => 'bagart/tgbot-module-nettools',
    'tgbot-module-proxy' => 'bagart/tgbot-module-proxy',
    'tgbot-module-stt' => 'bagart/tgbot-module-stt',
    'tgbot-module-summarizer' => 'bagart/tgbot-module-summarizer',
    'tgbot-module-tts' => 'bagart/tgbot-module-tts',
];

/** Local-only dirs allowed to keep a misc/ reference (doc comments only).
 * Empty: the example module was published as bagart/tgbot-module-example. */
$residualAllowlist = [];

$targets = [
    'phpunit.xml',
    'tests/Pest.php',
    'composer.prod.json',
    'config/tg_modules.php',
    'config/inertia.php',
    'app/Console/Commands/TgSpawnDaemonCommand.php',
    'package.json',
];

foreach ($targets as $file) {
    if (!is_file($file)) {
        fail("expected file missing: {$file}");
    }
}

foreach ($map as $pkg) {
    if (!is_dir('vendor/' . $pkg)) {
        fail("package not installed: vendor/{$pkg} (run cmd/deps/install --mode=prod --dev first)");
    }
}

$contents = [];
foreach ($targets as $file) {
    $contents[$file] = (string) file_get_contents($file);
}

// Longest dir first so prefix-overlapping names cannot corrupt each other.
uksort($map, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

foreach ($contents as $file => $content) {
    foreach ($map as $dir => $pkg) {
        $content = str_replace('misc/BAGArt/' . $dir, 'vendor/' . $pkg, $content);
    }
    $contents[$file] = $content;
}

// The bare <directory>misc</directory> in phpunit.xml <source> has no
// misc/BAGArt prefix for the generic map above. In vendor-mode checkouts it
// must point at the installed bagart packages: <source> drives both the
// coverage report include filter and pest-mutate's file set (via
// pathsFromPhpunitConfiguration), so leaving it on the empty misc/ makes
// mutation see zero library files.
$contents['phpunit.xml'] = preg_replace(
    '#<directory>misc</directory>#',
    '<directory>vendor/bagart</directory>',
    $contents['phpunit.xml'],
    1,
    $sourceRewrites,
);
if ($sourceRewrites !== 1) {
    fail("phpunit.xml: expected exactly one bare <directory>misc</directory> in <source>, found {$sourceRewrites}");
}

foreach ($contents as $file => $content) {
    preg_match_all('#misc/BAGArt/([A-Za-z0-9_-]+)#', $content, $matches);
    $leftovers = array_unique(array_diff($matches[1], array_keys($map), $residualAllowlist));
    if ($leftovers !== []) {
        fail("{$file}: unmapped misc/BAGArt references: " . implode(', ', $leftovers));
    }
}

foreach ($contents as $file => $content) {
    file_put_contents($file, $content);
    echo "{$file}: misc/BAGArt -> vendor rewritten\n";
}

putenv('COMPOSER=composer.prod.json');
passthru('composer dump-autoload --optimize --no-interaction', $code);
if ($code !== 0) {
    fail("composer dump-autoload failed (exit {$code})");
}

echo "rewrite-vendor-paths: done\n";
exit(EX_OK);
