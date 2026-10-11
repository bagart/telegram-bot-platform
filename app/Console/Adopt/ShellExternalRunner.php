<?php

declare(strict_types=1);

namespace App\Console\Adopt;

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

/**
 * Production ExternalRunner: shells out to the existing host tooling rather
 * than re-implementing any of its controls.
 */
final class ShellExternalRunner implements ExternalRunner
{
    private ?array $depsCheckResults = null;

    private bool $depsCheckRan = false;

    public function __construct(private readonly ?string $root = null)
    {
    }

    public function isAvailable(): bool
    {
        foreach (['bash', 'composer'] as $tool) {
            if (! $this->commandExists($tool)) {
                return false;
            }
        }

        return true;
    }

    public function depsCheck(): ?array
    {
        if ($this->depsCheckRan) {
            return $this->depsCheckResults;
        }

        $this->depsCheckRan = true;

        $process = new Process(['bash', 'cmd/deps/check', '--offline', '--format=json'], $this->root ?? base_path());
        $process->setTimeout(600);
        $process->run();

        $decoded = json_decode($process->getOutput(), true);

        if (! is_array($decoded) || ! isset($decoded['results']) || ! is_array($decoded['results'])) {
            return $this->depsCheckResults = null;
        }

        $byControl = [];
        foreach ($decoded['results'] as $row) {
            if (! is_array($row) || ! isset($row['control'], $row['status'])) {
                continue;
            }

            $byControl[(string) $row['control']] = [
                'status' => (string) $row['status'],
                'message' => (string) ($row['message'] ?? ''),
            ];
        }

        return $this->depsCheckResults = $byControl;
    }

    public function modulesValidate(): array
    {
        $exitCode = Artisan::call('tg:modules:validate');

        return ['exit_code' => $exitCode, 'output' => Artisan::output()];
    }

    public function prodLockIsPure(): bool
    {
        $library = base_path('cmd/deps/lib.sh');
        $lock = ($this->root ?? base_path()).'/composer.prod.lock';

        if (! is_file($library) || ! is_file($lock)) {
            return false;
        }

        $process = new Process([
            'bash', '-c',
            'err() { :; }; source "$1" && prod_lock_is_pure "$2"',
            'adopt',
            $library,
            $lock,
        ]);
        $process->setTimeout(60);
        $process->run();

        return $process->getExitCode() === 0;
    }

    private function commandExists(string $tool): bool
    {
        $process = new Process(['bash', '-c', sprintf('command -v %s', $tool)]);
        $process->setTimeout(15);
        $process->run();

        return $process->getExitCode() === 0;
    }
}
