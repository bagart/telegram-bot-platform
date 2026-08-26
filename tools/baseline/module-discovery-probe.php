<?php

declare(strict_types=1);

/**
 * Module discovery probe (devops3.md §B, landed 2026-08-24).
 *
 * Boots the host app from the current working directory and asserts that all
 * six composer-installed modules are discovered (registered into
 * telegram.modules_providers by their Laravel providers) and present in the
 * TgModuleRegistry singleton populated during bootstrap. Works identically in
 * dev mode (misc/ PSR-4) and prod mode (vendor packages), which is what makes
 * it usable as the prod-install acceptance probe.
 *
 * Usage (cwd must be the app root):
 *   php tools/baseline/module-discovery-probe.php [--format=text|json]
 *
 * Exit codes: 0 all modules discovered and booted, 1 discovery gap,
 * 2 usage/boot error.
 */

const EXIT_OK = 0;
const EXIT_CHECK = 1;
const EXIT_USAGE = 2;

const EXPECTED_MODULE_PROVIDERS = [
    'antispam' => \BAGArt\TelegramBotAntispam\AntispamModule::class,
    'summarizer' => \BAGArt\TelegramBotSummarizer\SummarizerModule::class,
    'nettools' => \BAGArt\TelegramBotNettools\NettoolsModule::class,
    'stt' => \BAGArt\TelegramBotStt\SttModule::class,
    'tts' => \BAGArt\TelegramBotTts\TtsModule::class,
    'mafia' => \BAGArt\TelegramBotMafia\MafiaModule::class,
];

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

$registered = (array) config('telegram.modules_providers', []);
$missing = [];
foreach (EXPECTED_MODULE_PROVIDERS as $id => $moduleClass) {
    if (! in_array($moduleClass, $registered, true)) {
        $missing[$id] = $moduleClass;
    }
}

// TelegramBotServiceProvider::bootModules() has already booted every
// discovered module into the registry singleton during bootstrap.
$registryIds = [];
$bootFailures = [];
try {
    $registry = $app->make(\BAGArt\TelegramBot\Modules\TgModuleRegistry::class);
    $registryIds = $registry->moduleIds();
    foreach (EXPECTED_MODULE_PROVIDERS as $id => $moduleClass) {
        if ($missing !== [] && isset($missing[$id])) {
            continue;
        }
        $descriptorId = $moduleClass::descriptor()->id;
        if (! $registry->has($descriptorId)) {
            $bootFailures[$id] = $descriptorId;
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, sprintf('registry read failed: %s%s', $e->getMessage(), PHP_EOL));

    exit(EXIT_USAGE);
}

$result = [
    'registered_providers' => array_values($registered),
    'registry_module_ids' => array_values($registryIds),
    'missing_providers' => $missing,
    'boot_failures' => $bootFailures,
];

if ($format === 'json') {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
} else {
    printf(
        'providers registered: %d/%d, registry modules: %d%s',
        count(array_intersect($registered, EXPECTED_MODULE_PROVIDERS)),
        count(EXPECTED_MODULE_PROVIDERS),
        count($registryIds),
        PHP_EOL,
    );
    foreach ($missing as $id => $class) {
        printf('MISSING provider %s (%s)%s', $id, $class, PHP_EOL);
    }
    foreach ($bootFailures as $id => $descriptorId) {
        printf('NOT BOOTED module id "%s" (%s)%s', $descriptorId, $id, PHP_EOL);
    }
}

exit($missing === [] && $bootFailures === [] ? EXIT_OK : EXIT_CHECK);
