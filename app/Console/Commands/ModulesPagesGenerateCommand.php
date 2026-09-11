<?php

declare(strict_types=1);

namespace App\Console\Commands;

use BAGArt\TelegramModuleEngine\Registry\EngineModuleRegistry;
use Illuminate\Console\Command;

/**
 * Host-level shim for module-owned frontend tooling: iterates the
 * pageGenerators registry from the EngineModuleRegistry so package.json
 * and CI reference only this neutral entry point — never a module
 * command name directly.
 *
 * Exit code: mirrors the first failing forwarded command (0 when all pass).
 */
final class ModulesPagesGenerateCommand extends Command
{
    protected $signature = 'modules:pages
        {--output= : Override the generated file path (defaults to <base>/resources/js/modules-pages.generated.ts)}';

    protected $description = 'Regenerate resources/js/modules-pages.generated.ts via the modules that own page generators';

    public function handle(): int
    {
        $generators = $this->registry()?->pageGenerators() ?? [];

        if ($generators === []) {
            $this->components->info('No module page generators registered; nothing to do.');

            return self::SUCCESS;
        }

        $arguments = [];
        $output = strval($this->option('output'));

        if ($output !== '') {
            $arguments['--output'] = $output;
        }

        $exit = self::SUCCESS;

        foreach ($generators as $command) {
            if ($this->call($command, $arguments) !== self::SUCCESS) {
                $exit = self::FAILURE;
            }
        }

        return $exit;
    }

    private function registry(): ?EngineModuleRegistry
    {
        if (! $this->laravel->bound(EngineModuleRegistry::class)) {
            return null;
        }

        $registry = $this->laravel->make(EngineModuleRegistry::class);

        return $registry instanceof EngineModuleRegistry ? $registry : null;
    }
}
