<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Adopt\AdoptAssertions;
use App\Console\Adopt\AdoptCheck;
use App\Console\Adopt\AdoptChecklist;
use App\Console\Adopt\AdoptGenerator;
use App\Console\Adopt\AdoptModule;
use App\Console\Adopt\AdoptRepository;
use App\Console\Adopt\ExternalRunner;
use App\Console\Adopt\ShellExternalRunner;
use Illuminate\Console\Command;
use Throwable;

/**
 * Host adoption command for a Telegram platform module (Q11 option 2).
 *
 * Three halves:
 *  - GENERATE — the 4 safe host artifacts (config/tg_modules.php entry,
 *    host Inertia page wrapper); never composer, lock or baseline mutations.
 *  - VALIDATE — 22 assertions over K20/K21/K22 wiring; 8 reuse an existing
 *    control and are reported under that control's id, 13 are new, 1 cited.
 *  - DOCUMENT — the operator-owned 12-step checklist, printed as text.
 *
 * Output and exit codes follow cmd/lib/contract.sh + cmd/lib/common.sh.
 */
final class ModuleAdoptCommand extends Command
{
    public const EX_OK = 0;

    public const EX_CHECK = 1;

    public const EX_USAGE = 2;

    public const EX_DEP = 3;

    public const EX_ENV = 4;

    public const EX_POLICY = 5;

    protected $signature = 'modules:adopt
        {id : Module id (descriptor id and config key)}
        {--check : Validate only — never write GENERATE artifacts}
        {--dry-run : Print the GENERATE writes without applying them}
        {--format=text : Output format: text|json}
        {--json : Alias for --format=json}';

    protected $description = 'Adopt a module into the host: scaffold safe artifacts, validate K20/K21/K22 wiring, print the operator-owned checklist';

    public function handle(): int
    {
        $format = $this->resolveFormat();

        if ($format === null) {
            $this->error("ERROR: invalid --format (expected text|json)");

            return self::EX_USAGE;
        }

        $repository = $this->laravel->make(AdoptRepository::class);

        if (! $repository->exists('composer.json')) {
            $this->error('ERROR: repository root not found (no composer.json)');

            return self::EX_ENV;
        }

        $id = (string) $this->argument('id');
        $module = $repository->resolveModule($id);

        if ($module === null) {
            $this->error(sprintf('ERROR: unknown module id "%s" (no package under misc/BAGArt and no config/tg_modules.php entry)', $id));

            return self::EX_USAGE;
        }

        $runner = $this->runner();

        if (! $runner->isAvailable()) {
            $this->error('ERROR: required command not found (bash, composer) — resource: modules:adopt');

            return self::EX_DEP;
        }

        try {
            return $this->runAdoption($repository, $runner, $module, $format);
        } catch (Throwable $exception) {
            $this->error('ERROR: '.$exception->getMessage());

            return self::EX_ENV;
        }
    }

    private function runAdoption(AdoptRepository $repository, ExternalRunner $runner, AdoptModule $module, string $format): int
    {
        $mode = $this->mode();
        $generator = new AdoptGenerator($repository);
        $results = [];

        if ($mode !== 'check') {
            $writes = $generator->plan($module);

            if ($mode === 'apply') {
                $generator->apply($writes);
            }

            foreach ($writes as $write) {
                if ($write->alreadyPresent) {
                    $results[] = new AdoptCheck('generate:'.$write->path, AdoptCheck::SKIPPED, 'already present — refused to overwrite');
                } elseif ($mode === 'apply') {
                    $results[] = new AdoptCheck('generate:'.$write->path, AdoptCheck::PASSED, 'written');
                } else {
                    $results[] = new AdoptCheck('generate:'.$write->path, AdoptCheck::PASSED, 'dry-run: not written');
                }
            }
        }

        $assertions = new AdoptAssertions($repository, $runner, $repository->loadModuleConfig());
        $checks = $assertions->run($module);

        foreach ($checks as $check) {
            $results[] = $check;
        }

        $exitCode = $this->anyFailed($checks) ? self::EX_CHECK : self::EX_OK;

        $this->render($results, $format);

        if ($format === 'json') {
            $this->output->writeln(json_encode([
                'exit_code' => $exitCode,
                'results' => $results,
            ], JSON_UNESCAPED_SLASHES));

            return $exitCode;
        }

        if ($this->option('verbose') || $this->anyFailed($checks)) {
            $this->renderChecklist($module);
        }

        return $exitCode;
    }

    /**
     * @param  list<AdoptCheck>  $results
     */
    private function render(array $results, string $format): void
    {
        if ($format === 'json') {
            return;
        }

        $quiet = (bool) $this->option('quiet');

        foreach ($results as $result) {
            if ($result->status === AdoptCheck::PASSED) {
                if (! $quiet) {
                    $this->line('✓ '.$result->control.' passed');
                }

                continue;
            }

            if ($result->status === AdoptCheck::SKIPPED) {
                if (! $quiet) {
                    $this->line('==> '.$result->control.' skipped'.($result->message !== '' ? ' — '.$result->message : ''));
                }

                continue;
            }

            $this->error('ERROR: '.$result->control.' failed'.($result->message !== '' ? ' — '.$result->message : ''));

            $remediation = AdoptAssertions::remediationFor($result->control);

            if ($remediation !== null) {
                $this->line('  remediation '.$result->control.': '.$remediation);
            }
        }
    }

    /**
     * @param  list<AdoptCheck>  $results
     */
    private function anyFailed(array $results): bool
    {
        foreach ($results as $result) {
            if ($result->failedCheck()) {
                return true;
            }
        }

        return false;
    }

    private function renderChecklist(AdoptModule $module): void
    {
        if ($this->option('quiet')) {
            return;
        }

        $this->newLine();
        $this->line(sprintf('Module %s (%s) — steps no adoption command may run:', $module->id, $module->dir));

        foreach (AdoptChecklist::steps($module) as $index => $step) {
            $this->line(sprintf('%2d. %s', $index + 1, $step));
        }
    }

    /** check | dry-run | apply */
    private function mode(): string
    {
        if ((bool) $this->option('check')) {
            return 'check';
        }

        if ((bool) $this->option('dry-run')) {
            return 'dry-run';
        }

        return 'apply';
    }

    private function resolveFormat(): ?string
    {
        if ((bool) $this->option('json')) {
            return 'json';
        }

        $format = (string) $this->option('format');

        return in_array($format, ['text', 'json'], true) ? $format : null;
    }

    private function runner(): ExternalRunner
    {
        if ($this->laravel->bound(ExternalRunner::class)) {
            return $this->laravel->make(ExternalRunner::class);
        }

        return new ShellExternalRunner();
    }
}
