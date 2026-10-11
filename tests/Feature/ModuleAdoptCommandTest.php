<?php

declare(strict_types=1);

use App\Console\Adopt\AdoptRepository;
use App\Console\Adopt\ExternalRunner;
use App\Console\Adopt\ShellExternalRunner;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Adoption contract for `modules:adopt` (docs/tasks/module-extensibility-w-d1-
 * adoption-tooling.md §Tests). Every VALIDATE/GENERATE case runs against a
 * self-contained fixture root plus a stub ExternalRunner, so the suite does not
 * depend on the nested package checkouts CI builds as empty stubs.
 */

function moduleAdoptSourceAvailable(): bool
{
    return is_file(base_path('misc/BAGArt/telegram-bot-lib/src/Modules/TgModuleDescriptor.php'));
}

function moduleAdoptRequireSources(object $test): void
{
    if (! moduleAdoptSourceAvailable()) {
        $test->markTestSkipped('nested module sources absent (CI stub checkout)');
    }
}

/**
 * @param  array<string, mixed>  $params
 * @return array{0: int, 1: array{exit_code?: int, results?: list<array{control: string, status: string, message: string}>}}
 */
function moduleAdoptInvoke(string $root, array $params, ?ExternalRunner $runner = null): array
{
    app()->instance(AdoptRepository::class, new AdoptRepository($root));
    app()->instance(ExternalRunner::class, $runner ?? moduleAdoptRunner());

    $buffer = new BufferedOutput();
    $exit = (int) Artisan::call('modules:adopt', $params, $buffer);
    $lines = array_values(array_filter(
        explode("\n", trim($buffer->fetch())),
        static fn (string $line): bool => str_starts_with($line, '{'),
    ));
    $payload = json_decode((string) end($lines), true);

    return [$exit, is_array($payload) ? $payload : []];
}

/**
 * @param  array<string, array{status: string, message: string}>|null  $deps
 * @param  array{exit_code: int, output: string}|null  $validate
 */
function moduleAdoptRunner(?array $deps = null, ?array $validate = null, bool $pure = true, bool $available = true): ExternalRunner
{
    $deps ??= [
        'lock-fresh:prod' => ['status' => 'passed', 'message' => ''],
        'lock-fresh:dev' => ['status' => 'passed', 'message' => ''],
        'deps-mode-purity' => ['status' => 'passed', 'message' => ''],
        'provider-wiring' => ['status' => 'passed', 'message' => ''],
        'module-layout:tgbot-fixture-module' => ['status' => 'passed', 'message' => ''],
    ];
    $validate ??= ['exit_code' => 0, 'output' => 'Module registry OK: 2 module(s) validated.'];

    return new class ($deps, $validate, $pure, $available) implements ExternalRunner {
        /**
         * @param  array<string, array{status: string, message: string}>  $deps
         * @param  array{exit_code: int, output: string}  $validate
         */
        public function __construct(
            private readonly array $deps,
            private readonly array $validate,
            private readonly bool $pure,
            private readonly bool $available,
        ) {
        }

        public function isAvailable(): bool
        {
            return $this->available;
        }

        public function depsCheck(): ?array
        {
            return $this->deps;
        }

        public function modulesValidate(): array
        {
            return $this->validate;
        }

        public function prodLockIsPure(): bool
        {
            return $this->pure;
        }
    };
}

/**
 * Build a repository root whose 22 controls pass for `adopt-fixture`.
 *
 * @return string absolute fixture root
 */
function moduleAdoptFixtureRoot(string $name, string $constraint = '*'): string
{
    $root = dirname(__DIR__, 2).'/storage/framework/testing/adopt/'.$name;

    if (is_dir($root)) {
        File::deleteDirectory($root);
    }

    File::makeDirectory($root, 0775, true);

    foreach (['composer.json', 'composer.prod.json', 'phpunit.xml', 'cmd/ci/rewrite-vendor-paths.php', 'config/tg_modules.php'] as $relative) {
        File::ensureDirectoryExists(dirname($root.'/'.$relative));
    }

    moduleAdoptWriteFixtureManifests($root, true, true);
    moduleAdoptWriteFixturePhpunit($root, true, true);
    moduleAdoptWriteFixtureRewriteScript($root, true, false);
    moduleAdoptWriteFixtureConfig($root, true);

    File::ensureDirectoryExists($root.'/misc/BAGArt/telegram-platform-workflows/.github/workflows');
    file_put_contents(
        $root.'/misc/BAGArt/telegram-platform-workflows/.github/workflows/nightly.yml',
        "env:\n  TG_MODULE_ENABLED_adopt-fixture: 'false'\n",
    );

    moduleAdoptWriteFixtureModule($root, $constraint);

    return $root;
}

function moduleAdoptWriteFixtureManifests(string $root, bool $withPathRepo, bool $withPsr4): void
{
    $repositories = $withPathRepo ? [['type' => 'path', 'url' => 'misc/BAGArt/tgbot-fixture-module']] : [];
    $psr4 = $withPsr4 ? ['AdoptFixture\\Src\\' => 'misc/BAGArt/tgbot-fixture-module/src/'] : [];

    file_put_contents($root.'/composer.json', json_encode([
        'name' => 'bagart/host-fixture',
        'autoload' => ['psr-4' => $psr4],
        'autoload-dev' => ['psr-4' => ['Tests\\' => 'tests/']],
        'require-dev' => ['laravel/pint' => '^1.27', 'pestphp/pest' => '^4.4'],
        'repositories' => $repositories,
        'scripts' => ['test' => ['@php artisan test']],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    file_put_contents($root.'/composer.prod.json', json_encode([
        'name' => 'bagart/host-fixture',
        'autoload' => ['psr-4' => $psr4],
        'autoload-dev' => ['psr-4' => ['Tests\\' => 'tests/']],
        'require' => ['bagart/tgbot-fixture-module' => '@dev'],
        'require-dev' => ['laravel/pint' => '^1.27', 'pestphp/pest' => '^4.4'],
        'repositories' => [['type' => 'vcs', 'url' => 'https://github.com/bagart/tgbot-fixture-module.git']],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function moduleAdoptWriteFixturePhpunit(string $root, bool $withEnvPin, bool $withServerPin): void
{
    $lines = [];

    if ($withEnvPin) {
        $lines[] = '            <env name="TG_MODULE_ENABLED_adopt-fixture" value="false" force="true" verbatim="true"/>';
    }

    if ($withServerPin) {
        $lines[] = '            <server name="TG_MODULE_ENABLED_adopt-fixture" value="false" verbatim="true"/>';
    }

    if ($lines === []) {
        $lines[] = '            <env name="APP_ENV" value="testing"/>';
    }

    file_put_contents($root.'/phpunit.xml', implode("\n", [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<phpunit>',
        '    <testsuites>',
        '        <testsuite name="FixtureModule">',
        '            <directory>tests</directory>',
        '        </testsuite>',
        '    </testsuites>',
        '    <php>',
        ...$lines,
        '    </php>',
        '</phpunit>',
        '',
    ]));
}

function moduleAdoptWriteFixtureRewriteScript(string $root, bool $withMapEntry, bool $withAllowlistEntry): void
{
    $map = $withMapEntry
        ? "    'tgbot-fixture-module' => 'bagart/tgbot-fixture-module',\n"
        : '';
    $allowlist = "    'tgbot-unrelated-module',\n"
        .($withAllowlistEntry ? "    'tgbot-fixture-module',\n" : '');

    file_put_contents($root.'/cmd/ci/rewrite-vendor-paths.php', <<<PHP
        <?php

        \$map = [
        {$map}        ];

        \$residualAllowlist = [
        {$allowlist}        ];
        PHP);
}

/**
 * Per-root fixture class name — every fixture root gets its own descriptor
 * class so the suite can require several roots in one process.
 */
function moduleAdoptFixtureClass(string $root): string
{
    $slug = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', basename($root))));

    return 'AdoptFixture\\Src\\AdoptFixture'.$slug.'Module';
}

function moduleAdoptWriteFixtureConfig(string $root, bool $withEntry): void
{
    $entries = $withEntry
        ? sprintf(
            "                'adopt-fixture' => new TgModuleConfig(\n                    enabled: true,\n                    provider: \\%s::class,\n                ),\n",
            moduleAdoptFixtureClass($root),
        )
        : '';

    file_put_contents($root.'/config/tg_modules.php', <<<PHP
        <?php

        use BAGArt\TelegramModuleEngine\Config\TgModuleConfig;

        return [
            'modules' => [
        {$entries}            ],
        ];
        PHP);
}

function moduleAdoptWriteFixtureModule(string $root, string $constraint): void
{
    $fqcn = moduleAdoptFixtureClass($root);
    $class = substr($fqcn, (int) strrpos($fqcn, '\\') + 1);
    $package = $root.'/misc/BAGArt/tgbot-fixture-module';
    File::ensureDirectoryExists($package);

    file_put_contents($package.'/composer.json', json_encode([
        'name' => 'bagart/tgbot-fixture-module',
        'autoload' => ['psr-4' => ['AdoptFixture\\Src\\' => 'src/']],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $path = $package.'/src/'.$class.'.php';
    File::ensureDirectoryExists(dirname($path));

    file_put_contents($path, <<<PHP
        <?php

        declare(strict_types=1);

        namespace AdoptFixture\Src;

        use BAGArt\TelegramBot\Modules\TgModuleContract;
        use BAGArt\TelegramBot\Modules\TgModuleDescriptor;
        use BAGArt\TelegramBot\Modules\TgModuleRegistrar;

        final class {$class} implements TgModuleContract
        {
            public static function descriptor(): TgModuleDescriptor
            {
                return new TgModuleDescriptor(
                    id: 'adopt-fixture',
                    name: 'Adopt Fixture',
                    version: '1.0.0',
                    requiresModules: ['menu' => '{$constraint}'],
                );
            }

            public static function register(TgModuleRegistrar \$registrar): void
            {
            }
        }
        PHP);
}

/** @return array<string, string> control => status */
function moduleAdoptStatuses(array $payload): array
{
    $statuses = [];

    foreach ($payload['results'] ?? [] as $row) {
        $statuses[$row['control']] = $row['status'];
    }

    return $statuses;
}

/** @return array<string, string> control => status */
function moduleAdoptCleanStatuses(): array
{
    return array_fill_keys([
        'prod-require-dev-mirror',
        'prod-autoload-dev-mirror',
        'prod-lock-fresh',
        'prod-install-no-dev',
        'dev-path-repo',
        'dev-psr-4',
        'dev-mode-purity',
        'dev-lock-fresh',
        'prod-require',
        'prod-vcs-repository',
        'prod-lock-purity',
        'host-testsuite',
        'root-test-chain',
        'phpunit-env-force',
        'phpunit-server-pin',
        'rewrite-map-entry',
        'residual-allowlist-absent',
        'ci-env-pin',
        'config-entry-present',
        'entry-shape-valid',
        'provider-registration',
        'descriptor-constraint-format',
    ], 'passed');
}

function moduleAdoptLoadFixtureModule(string $root): void
{
    $fqcn = moduleAdoptFixtureClass($root);

    if (class_exists($fqcn, false)) {
        return;
    }

    require_once $root.'/misc/BAGArt/tgbot-fixture-module/src/'.substr($fqcn, (int) strrpos($fqcn, '\\') + 1).'.php';
}

test('reports the full adoption contract as json on a clean fixture tree', function () {
    moduleAdoptRequireSources($this);

    $root = moduleAdoptFixtureRoot('clean');
    moduleAdoptLoadFixtureModule($root);

    [$exit, $payload] = moduleAdoptInvoke($root, ['id' => 'adopt-fixture', '--check' => true, '--format' => 'json']);

    expect($exit)->toBe(0)
        ->and($payload['exit_code'])->toBe(0)
        ->and($payload['results'])->toHaveCount(22)
        ->and(moduleAdoptStatuses($payload))->toBe(moduleAdoptCleanStatuses());

    foreach ($payload['results'] as $row) {
        expect(array_keys($row))->toBe(['control', 'status', 'message']);
    }
});

test('accepts --json as an alias for --format=json', function () {
    moduleAdoptRequireSources($this);

    $root = moduleAdoptFixtureRoot('json-alias');
    moduleAdoptLoadFixtureModule($root);

    [$exit, $payload] = moduleAdoptInvoke($root, ['id' => 'adopt-fixture', '--check' => true, '--json' => true]);

    expect($exit)->toBe(0)
        ->and($payload['results'])->toHaveCount(22);
});

test('exits 2 for an unknown module id', function () {
    moduleAdoptRequireSources($this);

    $root = moduleAdoptFixtureRoot('unknown');
    moduleAdoptLoadFixtureModule($root);

    [$exit] = moduleAdoptInvoke($root, ['id' => 'no-such-module', '--check' => true, '--format' => 'json']);

    expect($exit)->toBe(2);
});

test('exits 2 for an unsupported output format', function () {
    moduleAdoptRequireSources($this);

    $root = moduleAdoptFixtureRoot('bad-format');
    moduleAdoptLoadFixtureModule($root);

    [$exit] = moduleAdoptInvoke($root, ['id' => 'adopt-fixture', '--check' => true, '--format' => 'xml']);

    expect($exit)->toBe(2);
});

test('dry-run prints the generate writes without touching disk', function () {
    moduleAdoptRequireSources($this);

    $root = moduleAdoptFixtureRoot('dry-run');
    moduleAdoptWriteFixtureConfig($root, false);
    moduleAdoptWriteFixtureModule($root, '*');
    moduleAdoptLoadFixtureModule($root);

    $before = File::get($root.'/config/tg_modules.php');

    [$exit, $payload] = moduleAdoptInvoke($root, ['id' => 'adopt-fixture', '--dry-run' => true, '--format' => 'json']);

    $generate = array_values(array_filter(
        $payload['results'] ?? [],
        static fn (array $row): bool => str_starts_with($row['control'], 'generate:'),
    ));

    expect($generate)->toHaveCount(2)
        ->and($generate[0]['control'])->toBe('generate:config/tg_modules.php')
        ->and($generate[0]['status'])->toBe('passed')
        ->and($generate[0]['message'])->toBe('dry-run: not written')
        ->and(File::get($root.'/config/tg_modules.php'))->toBe($before)
        ->and(File::exists($root.'/resources/js/pages/Settings/AdoptFixture.tsx'))->toBeFalse()
        ->and($exit)->toBe(1);
});

test('apply writes the config entry and the host page wrapper', function () {
    moduleAdoptRequireSources($this);

    $root = moduleAdoptFixtureRoot('apply');
    moduleAdoptWriteFixtureConfig($root, false);
    moduleAdoptWriteFixtureModule($root, '*');
    moduleAdoptLoadFixtureModule($root);

    [$exit, $payload] = moduleAdoptInvoke($root, ['id' => 'adopt-fixture', '--format' => 'json']);

    $config = File::get($root.'/config/tg_modules.php');
    $page = File::get($root.'/resources/js/pages/Settings/AdoptFixture.tsx');

    expect($exit)->toBe(0)
        ->and($config)->toContain("'adopt-fixture' => new TgModuleConfig(")
        ->and($config)->toContain('provider: '.moduleAdoptFixtureClass($root).'::class,')
        ->and($config)->toContain("enabled: env('TG_MODULE_ENABLED_adopt-fixture', true),")
        ->and($page)->toContain('export default function AdoptFixtureSettings')
        ->and(moduleAdoptStatuses($payload)['generate:config/tg_modules.php'])->toBe('passed')
        ->and(moduleAdoptStatuses($payload)['generate:resources/js/pages/Settings/AdoptFixture.tsx'])->toBe('passed');
});

test('refuses to overwrite an existing config entry or page wrapper', function () {
    moduleAdoptRequireSources($this);

    $root = moduleAdoptFixtureRoot('refuse');
    moduleAdoptLoadFixtureModule($root);

    $before = File::get($root.'/config/tg_modules.php');
    $pagePath = $root.'/resources/js/pages/Settings/AdoptFixture.tsx';
    File::ensureDirectoryExists(dirname($pagePath));
    File::put($pagePath, "// pre-existing wrapper\n");
    $pageBefore = File::get($pagePath);

    [$exit, $payload] = moduleAdoptInvoke($root, ['id' => 'adopt-fixture', '--format' => 'json']);

    $statuses = moduleAdoptStatuses($payload);

    expect($statuses['generate:config/tg_modules.php'])->toBe('skipped')
        ->and($statuses['generate:resources/js/pages/Settings/AdoptFixture.tsx'])->toBe('skipped')
        ->and(File::get($root.'/config/tg_modules.php'))->toBe($before)
        ->and(File::get($pagePath))->toBe($pageBefore);
});

/**
 * Remove exactly one K20/K21/K22 wiring fact from a clean fixture tree, so the
 * VALIDATE half can prove that only its own control flips.
 */
function moduleAdoptBreakFixture(string $root, string $case): void
{
    $edit = static function (string $path, callable $mutate): void {
        $data = json_decode((string) File::get($path), true);
        $mutate($data);
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    };

    switch ($case) {
        case 'a1':
            $edit($root.'/composer.prod.json', static function (array &$data): void {
                unset($data['require-dev']['pestphp/pest']);
            });
            break;

        case 'a1v':
            $edit($root.'/composer.prod.json', static function (array &$data): void {
                $data['require-dev']['pestphp/pest'] = '3.0.0';
            });
            break;

        case 'a2':
            $edit($root.'/composer.prod.json', static function (array &$data): void {
                unset($data['autoload-dev']['psr-4']['Tests\\']);
            });
            break;

        case 'b1':
            $edit($root.'/composer.json', static function (array &$data): void {
                $data['repositories'] = [];
            });
            break;

        case 'b2':
            $edit($root.'/composer.json', static function (array &$data): void {
                $data['autoload']['psr-4'] = [];
            });
            break;

        case 'b5':
            $edit($root.'/composer.prod.json', static function (array &$data): void {
                $data['require'] = [];
            });
            break;

        case 'b6':
            $edit($root.'/composer.prod.json', static function (array &$data): void {
                $data['repositories'] = [];
            });
            break;

        case 'c3':
            moduleAdoptWriteFixturePhpunit($root, false, true);
            break;

        case 'c4':
            moduleAdoptWriteFixturePhpunit($root, true, false);
            break;

        case 'c5':
            moduleAdoptWriteFixtureRewriteScript($root, false, false);
            break;

        case 'c6':
            moduleAdoptWriteFixtureRewriteScript($root, true, true);
            break;

        case 'c7':
            File::delete($root.'/misc/BAGArt/telegram-platform-workflows/.github/workflows/nightly.yml');
            break;

        case 'd1':
            moduleAdoptWriteFixtureConfig($root, false);
            break;

        default:
            throw new InvalidArgumentException('unknown fixture break case: '.$case);
    }
}

test('breaks exactly one adoption control per missing wiring fact', function (string $case, string $control, string $status, int $exit) {
    moduleAdoptRequireSources($this);

    $root = moduleAdoptFixtureRoot('neg-'.$case, $case === 'd4' ? '^1.0' : '*');
    moduleAdoptLoadFixtureModule($root);

    if ($case !== 'd4') {
        moduleAdoptBreakFixture($root, $case);
    }

    [$code, $payload] = moduleAdoptInvoke($root, ['id' => 'adopt-fixture', '--check' => true, '--format' => 'json']);
    $statuses = moduleAdoptStatuses($payload);
    $unexpected = array_diff_key(
        array_filter($statuses, static fn (string $rowStatus): bool => $rowStatus !== 'passed'),
        [$control => $status],
    );

    expect($payload['results'])->toHaveCount(22)
        ->and($statuses[$control] ?? null)->toBe($status)
        ->and($unexpected)->toBe([])
        ->and($code)->toBe($exit);
})->with([
    ['a1', 'prod-require-dev-mirror', 'failed', 1],
    ['a1v', 'prod-require-dev-mirror', 'failed', 1],
    ['a2', 'prod-autoload-dev-mirror', 'failed', 1],
    ['b1', 'dev-path-repo', 'failed', 1],
    ['b2', 'dev-psr-4', 'failed', 1],
    ['b5', 'prod-require', 'failed', 1],
    ['b6', 'prod-vcs-repository', 'failed', 1],
    ['c3', 'phpunit-env-force', 'failed', 1],
    ['c4', 'phpunit-server-pin', 'failed', 1],
    ['c5', 'rewrite-map-entry', 'failed', 1],
    ['c6', 'residual-allowlist-absent', 'failed', 1],
    ['c7', 'ci-env-pin', 'skipped', 0],
    ['d1', 'config-entry-present', 'failed', 1],
    ['d4', 'descriptor-constraint-format', 'failed', 1],
]);

test('reports a failed reused control under its adoption id with the original message', function () {
    moduleAdoptRequireSources($this);

    $root = moduleAdoptFixtureRoot('reuse');
    moduleAdoptLoadFixtureModule($root);

    [$exit, $payload] = moduleAdoptInvoke(
        $root,
        ['id' => 'adopt-fixture', '--check' => true, '--format' => 'json'],
        moduleAdoptRunner([
            'lock-fresh:prod' => ['status' => 'failed', 'message' => 'composer.prod.lock is stale'],
            'lock-fresh:dev' => ['status' => 'passed', 'message' => ''],
            'deps-mode-purity' => ['status' => 'passed', 'message' => ''],
            'provider-wiring' => ['status' => 'passed', 'message' => ''],
            'module-layout:tgbot-fixture-module' => ['status' => 'passed', 'message' => ''],
        ]),
    );
    $messages = array_column($payload['results'], 'message', 'control');

    expect($exit)->toBe(1)
        ->and(moduleAdoptStatuses($payload)['prod-lock-fresh'])->toBe('failed')
        ->and($messages['prod-lock-fresh'])->toBe('composer.prod.lock is stale');
});

test('falls back to skipped when cmd/deps/check reports no payload', function () {
    moduleAdoptRequireSources($this);

    $root = moduleAdoptFixtureRoot('deps-missing');
    moduleAdoptLoadFixtureModule($root);

    [$exit, $payload] = moduleAdoptInvoke(
        $root,
        ['id' => 'adopt-fixture', '--check' => true, '--format' => 'json'],
        moduleAdoptRunner([]),
    );
    $statuses = moduleAdoptStatuses($payload);

    expect($exit)->toBe(0)
        ->and($statuses['prod-lock-fresh'])->toBe('skipped')
        ->and($statuses['dev-mode-purity'])->toBe('skipped')
        ->and($statuses['dev-lock-fresh'])->toBe('skipped')
        ->and($statuses['host-testsuite'])->toBe('skipped')
        ->and($statuses['root-test-chain'])->toBe('skipped')
        ->and($statuses['provider-registration'])->toBe('skipped')
        ->and($statuses['prod-lock-purity'])->toBe('passed');
});

test('exits 3 when a required tool is unavailable', function () {
    moduleAdoptRequireSources($this);

    $root = moduleAdoptFixtureRoot('no-tool');
    moduleAdoptLoadFixtureModule($root);

    [$exit] = moduleAdoptInvoke(
        $root,
        ['id' => 'adopt-fixture', '--check' => true, '--format' => 'json'],
        moduleAdoptRunner(null, null, true, false),
    );

    expect($exit)->toBe(3);
});

test('validates the real repository tree green', function () {
    moduleAdoptRequireSources($this);

    if (! is_file(base_path('misc/BAGArt/tgbot-module-example/composer.json'))) {
        $this->markTestSkipped('example package absent (CI stub checkout)');
    }

    [$exit, $payload] = moduleAdoptInvoke(
        base_path(),
        ['id' => 'example', '--check' => true, '--format' => 'json'],
        new ShellExternalRunner(base_path()),
    );

    expect($exit)->toBe(0)
        ->and($payload['results'])->toHaveCount(22)
        ->and(array_filter(
            $payload['results'],
            static fn (array $row): bool => $row['status'] !== 'passed',
        ))->toBe([]);
});

test('keeps the W-D1 host wiring facts in place', function () {
    expect(File::get(base_path('composer.json')))->toContain('cd misc/BAGArt/tgbot-module-example && composer test')
        ->and(File::get(base_path('phpunit.xml')))->toContain('<testsuite name="ExampleModule">')
        ->and(File::get(base_path('docker/common/php-fpm/Dockerfile')))->toContain(' ask-queue ')
        ->and(File::get(base_path('docker/common/php-fpm/Dockerfile')))->toContain('tgbot-module-example')
        ->and(File::get(base_path('bootstrap/providers.php')))->toContain('ProxyOperationsServiceProvider');
});
