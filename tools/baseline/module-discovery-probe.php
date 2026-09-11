<?php

declare(strict_types=1);

/**
 * Module discovery probe (devops3.md §B, landed 2026-08-24).
 *
 * Boots the host app from the current working directory and asserts that all
 * modules declared in config/tg_modules.php are registered in the engine
 * registry and available for dispatch. Works identically in dev mode
 * (misc/ PSR-4) and prod mode (vendor packages).
 *
 * Usage (cwd must be the app root):
 *   php tools/baseline/module-discovery-probe.php [--format=text|json]
 *
 * Exit codes: 0 all modules discovered, 1 discovery gap, 2 usage/boot error.
 */

const EXIT_OK = 0;
const EXIT_CHECK = 1;
const EXIT_USAGE = 2;

$format = 'text';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--format=json' || $arg === '--json') {
        $format = 'json';

        continue;
    }
    if (in_array($arg, ['--format=text', '--help', '-h'], true)) {
        continue;
    }
    fwrite(STDERR, sprintf('unknown argument: %s%s', $arg, PHP_EOL));

    exit(EXIT_USAGE);
}

if (! is_file(getcwd().'/bootstrap/app.php')) {
    fwrite(STDERR, 'run from the app root (bootstrap/app.php not found)'.PHP_EOL);

    exit(EXIT_USAGE);
}

require getcwd().'/vendor/autoload.php';

$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Read expected modules from the declarative config (single source of truth).
$expectedModules = (array) config('tg_modules.modules', []);
$expectedIds = array_keys($expectedModules);

// Validate via the engine registry (builds from config, checks wiring).
$registryErrors = [];
$registryModuleIds = [];
try {
    $builder = new \BAGArt\TelegramModuleEngine\Registry\ModuleRegistryBuilder(config('tg_modules'));
    $result = $builder->build();
    $registryModuleIds = array_keys($result->registry->all());
    $registryErrors = array_map(
        static fn (\BAGArt\TelegramModuleEngine\Registry\RegistryError $e): string => $e->message,
        $result->errors,
    );
} catch (Throwable $e) {
    fwrite(STDERR, sprintf('registry build failed: %s%s', $e->getMessage(), PHP_EOL));

    exit(EXIT_USAGE);
}

// Check for config modules not in registry (discovery gap).
$missing = [];
foreach ($expectedIds as $id) {
    if (! in_array($id, $registryModuleIds, true)) {
        $missing[$id] = $expectedModules[$id]->provider ?? 'unknown';
    }
}

// Check for registry modules not in config (stale entries).
$stale = [];
foreach ($registryModuleIds as $id) {
    if (! isset($expectedModules[$id])) {
        $stale[] = $id;
    }
}

$result = [
    'config_modules' => $expectedIds,
    'registry_modules' => $registryModuleIds,
    'missing_modules' => $missing,
    'stale_modules' => $stale,
    'registry_errors' => $registryErrors,
];

if ($format === 'json') {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
} else {
    printf(
        'config modules: %d, registry modules: %d%s',
        count($expectedIds),
        count($registryModuleIds),
        PHP_EOL,
    );
    foreach ($missing as $id => $provider) {
        printf('MISSING module "%s" (%s)%s', $id, $provider, PHP_EOL);
    }
    foreach ($stale as $id) {
        printf('STALE module "%s" (in registry but not in config)%s', $id, PHP_EOL);
    }
    foreach ($registryErrors as $error) {
        printf('REGISTRY ERROR: %s%s', $error, PHP_EOL);
    }
}

exit($missing === [] && $stale === [] && $registryErrors === [] ? EXIT_OK : EXIT_CHECK);
