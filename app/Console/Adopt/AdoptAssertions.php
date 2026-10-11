<?php

declare(strict_types=1);

namespace App\Console\Adopt;

use BAGArt\TelegramBot\Modules\TgModuleContract;
use Throwable;

/**
 * The VALIDATE half of `modules:adopt`: 22 assertions over the K20/K21/K22
 * wiring contract (docs/researches/module-extensibility-d1-validate-mode-pack.md §2).
 *
 * Split is binding: 8 reuse an existing control and are reported under that
 * control's id, 13 are new, 1 (A4) is a cited repo-global invariant.
 * Nothing here mutates composer, locks or baselines — failing rows only carry
 * remediation text the operator runs themselves (Q11 Decision).
 */
final class AdoptAssertions
{
    public const CONTROL_A1 = 'prod-require-dev-mirror';

    public const CONTROL_A2 = 'prod-autoload-dev-mirror';

    public const CONTROL_A3 = 'prod-lock-fresh';

    public const CONTROL_A4 = 'prod-install-no-dev';

    public const CONTROL_B1 = 'dev-path-repo';

    public const CONTROL_B2 = 'dev-psr-4';

    public const CONTROL_B3 = 'dev-mode-purity';

    public const CONTROL_B4 = 'dev-lock-fresh';

    public const CONTROL_B5 = 'prod-require';

    public const CONTROL_B6 = 'prod-vcs-repository';

    public const CONTROL_B7 = 'prod-lock-purity';

    public const CONTROL_C1 = 'host-testsuite';

    public const CONTROL_C2 = 'root-test-chain';

    public const CONTROL_C3 = 'phpunit-env-force';

    public const CONTROL_C4 = 'phpunit-server-pin';

    public const CONTROL_C5 = 'rewrite-map-entry';

    public const CONTROL_C6 = 'residual-allowlist-absent';

    public const CONTROL_C7 = 'ci-env-pin';

    public const CONTROL_D1 = 'config-entry-present';

    public const CONTROL_D2 = 'entry-shape-valid';

    public const CONTROL_D3 = 'provider-registration';

    public const CONTROL_D4 = 'descriptor-constraint-format';

    public const WORKFLOWS_NIGHTLY = 'misc/BAGArt/telegram-platform-workflows/.github/workflows/nightly.yml';

    /** @var array<string, array{status: string, message: string}>|null */
    private ?array $depsCheckResults = null;

    private bool $depsCheckRan = false;

    /** @var array{exit_code: int, output: string}|null */
    private ?array $modulesValidateResult = null;

    /**
     * @param  array<string, mixed>  $moduleConfig  raw config/tg_modules.php payload from the repository root
     */
    public function __construct(
        private readonly AdoptRepository $repository,
        private readonly ExternalRunner $runner,
        private readonly array $moduleConfig = [],
    ) {
    }

    /**
     * @return list<AdoptCheck>
     */
    public function run(AdoptModule $module): array
    {
        return [
            ...$this->k20(),
            ...$this->k21($module),
            ...$this->k22($module),
            ...$this->configGroup($module),
        ];
    }

    /**
     * Operator-owned command printed under a failing control. Never executed here.
     */
    public static function remediationFor(string $control): ?string
    {
        return match ($control) {
            self::CONTROL_A1,
            self::CONTROL_A2,
            self::CONTROL_B1,
            self::CONTROL_B2,
            self::CONTROL_B4,
            self::CONTROL_B5,
            self::CONTROL_B6,
            self::CONTROL_A3 => 'bash cmd/deps/update --mode=both',
            self::CONTROL_B3 => 'bash cmd/deps/check',
            self::CONTROL_B7 => 'bash cmd/deps/update --mode=both',
            self::CONTROL_C1 => 'add <testsuite><directory>misc/BAGArt/<dir>/tests</directory></testsuite> to phpunit.xml, then bash cmd/deps/check',
            self::CONTROL_C2 => 'add "cd misc/BAGArt/<dir> && composer test" to composer.json scripts.test, review, then php tools/baseline/composer-policy.php --check=scripts --update',
            self::CONTROL_C3,
            self::CONTROL_C4 => 'add the TG_MODULE_ENABLED_<id> env/server pin to phpunit.xml (see the in-file comment block)',
            self::CONTROL_C5 => "add '<dir>' => 'bagart/<dir>' to \$map in cmd/ci/rewrite-vendor-paths.php",
            self::CONTROL_C6 => "remove '<dir>' from \$residualAllowlist in cmd/ci/rewrite-vendor-paths.php",
            self::CONTROL_D1 => 'php artisan modules:adopt <id>   (GENERATE half scaffolds the config entry)',
            self::CONTROL_D2 => 'php artisan tg:modules:validate',
            self::CONTROL_D3 => 'bash cmd/deps/check',
            self::CONTROL_D4 => "declare requiresModules with *, >=, <=, >, < or = (no ^ or ~)",
            default => null,
        };
    }

    /** @return list<AdoptCheck> */
    private function k20(): array
    {
        $dev = $this->repository->json('composer.json');
        $prod = $this->repository->json('composer.prod.json');

        $checks = [];
        $checks[] = $this->mirrorCheck(
            self::CONTROL_A1,
            'require-dev',
            is_array($dev) ? ($dev['require-dev'] ?? null) : null,
            is_array($prod) ? ($prod['require-dev'] ?? null) : null,
        );
        $checks[] = $this->mirrorCheck(
            self::CONTROL_A2,
            'autoload-dev psr-4',
            is_array($dev) ? ($dev['autoload-dev']['psr-4'] ?? null) : null,
            is_array($prod) ? ($prod['autoload-dev']['psr-4'] ?? null) : null,
        );
        $checks[] = $this->reuse(self::CONTROL_A3, 'lock-fresh:prod');
        $checks[] = AdoptCheck::passed(
            self::CONTROL_A4,
            'repo-global invariant: cmd/deps/install --mode=prod passes --no-dev (cmd/deps/install:6-11)',
        );

        return $checks;
    }

    /** @return list<AdoptCheck> */
    private function k21(AdoptModule $module): array
    {
        $dev = $this->repository->json('composer.json') ?? [];
        $prod = $this->repository->json('composer.prod.json') ?? [];
        $expectedPath = 'misc/BAGArt/'.$module->dir;
        $expectedVcs = 'https://github.com/bagart/'.$module->dir.'.git';

        $checks = [];

        $pathHit = false;
        foreach ((array) ($dev['repositories'] ?? []) as $repository) {
            if (! is_array($repository) || ($repository['type'] ?? null) !== 'path') {
                continue;
            }

            $url = rtrim(strval($repository['url'] ?? ''), '/');

            if ($url === $expectedPath || $url === './'.$expectedPath) {
                $pathHit = true;
                break;
            }
        }
        $checks[] = $pathHit
            ? AdoptCheck::passed(self::CONTROL_B1)
            : AdoptCheck::failed(self::CONTROL_B1, sprintf('dev path repository missing: %s', $expectedPath));

        $psr4 = (array) ($dev['autoload']['psr-4'] ?? []);
        $prefix = $module->namespace.'\\';
        $mapped = null;
        foreach ($psr4 as $key => $value) {
            if (is_string($key) && rtrim($key, '\\').'\\' === $prefix) {
                $mapped = rtrim(strval($value), '/');
                break;
            }
        }
        $checks[] = ($mapped !== null && str_ends_with($mapped, $module->dir.'/src'))
            ? AdoptCheck::passed(self::CONTROL_B2)
            : AdoptCheck::failed(
                self::CONTROL_B2,
                sprintf('dev psr-4 mapping missing: %s => %s/src/', $module->namespace, $expectedPath),
            );

        $checks[] = $this->reuse(self::CONTROL_B3, 'deps-mode-purity');
        $checks[] = $this->reuse(self::CONTROL_B4, 'lock-fresh:dev');

        $checks[] = array_key_exists('bagart/'.$module->dir, (array) ($prod['require'] ?? []))
            ? AdoptCheck::passed(self::CONTROL_B5)
            : AdoptCheck::failed(self::CONTROL_B5, sprintf('prod require missing: bagart/%s', $module->dir));

        $vcsHit = false;
        foreach ((array) ($prod['repositories'] ?? []) as $repository) {
            if (! is_array($repository) || ($repository['type'] ?? null) !== 'vcs') {
                continue;
            }

            if (rtrim(strval($repository['url'] ?? ''), '/') === $expectedVcs) {
                $vcsHit = true;
                break;
            }
        }
        $checks[] = $vcsHit
            ? AdoptCheck::passed(self::CONTROL_B6)
            : AdoptCheck::failed(self::CONTROL_B6, sprintf('prod VCS repository missing: %s', $expectedVcs));

        $checks[] = $this->runner->prodLockIsPure()
            ? AdoptCheck::passed(self::CONTROL_B7)
            : AdoptCheck::failed(
                self::CONTROL_B7,
                'prod lock references path sources (misc/BAGArt) or symlinked packages',
            );

        return $checks;
    }

    /** @return list<AdoptCheck> */
    private function k22(AdoptModule $module): array
    {
        $checks = [];

        [$suiteStatus, $suiteMessage] = $this->moduleLayoutIssue(
            $module,
            'no host phpunit.xml suite',
            'host phpunit.xml testsuite present for misc/BAGArt/'.$module->dir.'/tests',
        );
        $checks[] = new AdoptCheck(self::CONTROL_C1, $suiteStatus, $suiteMessage);

        [$chainStatus, $chainMessage] = $this->moduleLayoutIssue(
            $module,
            'not chained in root composer test',
            'root composer test chain entry present for misc/BAGArt/'.$module->dir,
        );
        $checks[] = new AdoptCheck(self::CONTROL_C2, $chainStatus, $chainMessage);

        $phpunit = $this->repository->exists('phpunit.xml') ? $this->repository->read('phpunit.xml') : '';
        $pin = 'TG_MODULE_ENABLED_'.$module->id;

        $checks[] = $this->attributeCheck(
            self::CONTROL_C3,
            'env',
            $pin,
            $phpunit,
            ['force' => 'true', 'verbatim' => 'true'],
            sprintf('phpunit.xml missing force/verbatim env pin for %s', $pin),
        );
        $checks[] = $this->attributeCheck(
            self::CONTROL_C4,
            'server',
            $pin,
            $phpunit,
            ['verbatim' => 'true'],
            sprintf('phpunit.xml missing matching <server> pin (env() resolves $_SERVER first) for %s', $pin),
        );

        $map = $this->scriptArrayBody('map');
        $checks[] = $map === null
            ? AdoptCheck::failed(self::CONTROL_C5, 'cmd/ci/rewrite-vendor-paths.php: $map array not found')
            : (str_contains($map, "'".$module->dir."' =>")
                ? AdoptCheck::passed(self::CONTROL_C5)
                : AdoptCheck::failed(
                    self::CONTROL_C5,
                    sprintf('rewrite map missing entry: %s => bagart/%s', $module->dir, $module->dir),
                ));

        $allowlist = $this->scriptArrayBody('residualAllowlist');
        $checks[] = $allowlist === null
            ? AdoptCheck::failed(self::CONTROL_C6, 'cmd/ci/rewrite-vendor-paths.php: $residualAllowlist array not found')
            : (! preg_match("/'".preg_quote($module->dir, '/')."'/", $allowlist)
                ? AdoptCheck::passed(self::CONTROL_C6)
                : AdoptCheck::failed(
                    self::CONTROL_C6,
                    sprintf('dir still listed in $residualAllowlist — CI will not rewrite its misc/ refs: %s', $module->dir),
                ));

        $checks[] = $this->ciEnvPin($module);

        return $checks;
    }

    /** @return list<AdoptCheck> */
    private function configGroup(AdoptModule $module): array
    {
        $checks = [];

        $entries = $this->moduleConfig['modules'] ?? [];
        $entry = is_array($entries) ? ($entries[$module->id] ?? null) : null;
        $checks[] = $entry !== null
            ? AdoptCheck::passed(self::CONTROL_D1)
            : AdoptCheck::failed(
                self::CONTROL_D1,
                sprintf('config/tg_modules.php: no entry for module "%s"', $module->id),
            );

        $validate = $this->modulesValidate();
        $summary = trim($validate['output']);
        $checks[] = $validate['exit_code'] === 0
            ? AdoptCheck::passed(self::CONTROL_D2, strtok($summary, "\n") ?: '')
            : AdoptCheck::failed(self::CONTROL_D2, str_replace("\n", '; ', $summary));

        $checks[] = $this->reuse(self::CONTROL_D3, 'provider-wiring');

        $checks[] = $this->descriptorConstraintFormat($module);

        return $checks;
    }

    /**
     * @param  array<string, mixed>|null  $devValue
     * @param  array<string, mixed>|null  $prodValue
     */
    private function mirrorCheck(string $control, string $section, ?array $devValue, ?array $prodValue): AdoptCheck
    {
        if ($devValue === null || $prodValue === null) {
            return AdoptCheck::failed($control, sprintf('%s missing from a manifest (%s)', $section, $this->repository->path('composer.json')));
        }

        ksort($devValue);
        ksort($prodValue);

        $missing = array_diff_key($devValue, $prodValue);
        $extra = array_diff_key($prodValue, $devValue);
        $drifted = [];

        foreach (array_intersect_key($devValue, $prodValue) as $key => $value) {
            if ($value !== $prodValue[$key]) {
                $drifted[] = (string) $key;
            }
        }

        if ($missing === [] && $extra === [] && $drifted === []) {
            return AdoptCheck::passed($control);
        }

        $parts = [];
        if ($missing !== []) {
            $parts[] = 'missing in prod: '.implode(', ', array_keys($missing));
        }
        if ($extra !== []) {
            $parts[] = 'missing in dev: '.implode(', ', array_keys($extra));
        }
        if ($drifted !== []) {
            $parts[] = 'value drift: '.implode(', ', $drifted);
        }

        return AdoptCheck::failed($control, sprintf('prod %s out of sync with dev (%s)', $section, implode('; ', $parts)));
    }

    private function reuse(string $control, string $externalControl): AdoptCheck
    {
        $results = $this->depsCheck();

        if ($results === null) {
            return AdoptCheck::skipped($control, 'cmd/deps/check produced no parseable payload');
        }

        if (! isset($results[$externalControl])) {
            return AdoptCheck::skipped($control, sprintf('cmd/deps/check did not report "%s"', $externalControl));
        }

        $row = $results[$externalControl];

        if ($row['status'] === 'passed') {
            return AdoptCheck::passed($control, sprintf('reused cmd/deps/check "%s"', $externalControl));
        }

        if ($row['status'] === 'skipped') {
            return AdoptCheck::skipped($control, $row['message'] !== '' ? $row['message'] : sprintf('cmd/deps/check "%s" skipped', $externalControl));
        }

        return AdoptCheck::failed($control, $row['message'] !== '' ? $row['message'] : sprintf('cmd/deps/check "%s" failed', $externalControl));
    }

    /**
     * Derive a specific module-layout fact from cmd/deps/check's aggregated
     * `module-layout:<dir>` issue string instead of re-checking it here.
     *
     * @return array{0: string, 1: string} [status, message]
     */
    private function moduleLayoutIssue(AdoptModule $module, string $issueToken, string $passMessage): array
    {
        $results = $this->depsCheck();

        if ($results === null) {
            return [AdoptCheck::SKIPPED, 'cmd/deps/check produced no parseable payload'];
        }

        $control = 'module-layout:'.$module->dir;

        if (! isset($results[$control])) {
            return [AdoptCheck::SKIPPED, 'cmd/deps/check does not gate this package (needs composer.json + tests/)'];
        }

        $row = $results[$control];

        if ($row['status'] === 'passed') {
            return [AdoptCheck::PASSED, sprintf('reused cmd/deps/check "%s"', $control)];
        }

        if ($row['status'] === 'skipped') {
            return [AdoptCheck::SKIPPED, $row['message']];
        }

        $issues = $row['message'];

        if (str_contains($issues, $issueToken)) {
            return [AdoptCheck::FAILED, sprintf('reused cmd/deps/check "%s": %s', $control, $issues)];
        }

        return [AdoptCheck::PASSED, sprintf('reused cmd/deps/check "%s" (%s not reported)', $control, $issueToken)];
    }

    /**
     * @param  array<string, string>  $requiredAttributes
     */
    private function attributeCheck(
        string $control,
        string $element,
        string $pin,
        string $xml,
        array $requiredAttributes,
        string $failureMessage,
    ): AdoptCheck {
        if ($xml === '') {
            return AdoptCheck::failed($control, 'phpunit.xml missing or unreadable');
        }

        $pattern = sprintf('/<%s\s+name="%s"[^>]*>/i', $element, preg_quote($pin, '/'));

        if (preg_match($pattern, $xml, $match) !== 1) {
            return AdoptCheck::failed($control, $failureMessage);
        }

        foreach ($requiredAttributes as $attribute => $expected) {
            if (preg_match(sprintf('/\s%s="([^"]*)"/', $attribute), $match[0], $value) !== 1 || $value[1] !== $expected) {
                return AdoptCheck::failed($control, $failureMessage);
            }
        }

        return AdoptCheck::passed($control);
    }

    private function ciEnvPin(AdoptModule $module): AdoptCheck
    {
        if (! $this->repository->exists(self::WORKFLOWS_NIGHTLY)) {
            return AdoptCheck::skipped(self::CONTROL_C7, 'workflows package worktree absent — document step 9');
        }

        $contents = $this->repository->read(self::WORKFLOWS_NIGHTLY);
        $pin = 'TG_MODULE_ENABLED_'.$module->id;

        if (str_contains($contents, $pin)) {
            return AdoptCheck::passed(self::CONTROL_C7);
        }

        return AdoptCheck::skipped(self::CONTROL_C7, sprintf('no CI pin for %s — judgment step (row 22)', $pin));
    }

    private function descriptorConstraintFormat(AdoptModule $module): AdoptCheck
    {
        if (! class_exists($module->provider) || ! is_a($module->provider, TgModuleContract::class, true)) {
            return AdoptCheck::skipped(self::CONTROL_D4, 'provider class is not loadable in this process');
        }

        try {
            $descriptor = $module->provider::descriptor();
        } catch (Throwable $exception) {
            return AdoptCheck::skipped(self::CONTROL_D4, 'descriptor() threw: '.$exception->getMessage());
        }

        foreach ($descriptor->requiresModules as $dependency => $constraint) {
            $text = trim((string) $constraint);

            if ($text === '*' || preg_match('/^(>=|<=|>|<|=)\s*.+$/', $text) === 1) {
                continue;
            }

            return AdoptCheck::failed(
                self::CONTROL_D4,
                sprintf('requiresModules "%s" uses unsupported operator (use *, >=, <=, >, <, =)', $dependency.'@'.$text),
            );
        }

        return AdoptCheck::passed(self::CONTROL_D4);
    }

    /** Extract a literal top-level array body from cmd/ci/rewrite-vendor-paths.php (static parse — the script cannot be executed). */
    private function scriptArrayBody(string $variable): ?string
    {
        if (! $this->repository->exists('cmd/ci/rewrite-vendor-paths.php')) {
            return null;
        }

        $source = $this->repository->read('cmd/ci/rewrite-vendor-paths.php');

        if (preg_match('/\$'.preg_quote($variable, '/').'\s*=\s*\[(.*?)\];/s', $source, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    /** @return array<string, array{status: string, message: string}>|null */
    private function depsCheck(): ?array
    {
        if (! $this->depsCheckRan) {
            $this->depsCheckRan = true;
            $this->depsCheckResults = $this->runner->depsCheck();
        }

        return $this->depsCheckResults;
    }

    /** @return array{exit_code: int, output: string} */
    private function modulesValidate(): array
    {
        return $this->modulesValidateResult ??= $this->runner->modulesValidate();
    }
}
