<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Host-level shim for module-owned frontend tooling: iterates the
 * `telegram.modules_page_generators` registry (populated by the module engine
 * from the modules' declarative pageGenerators entries) so package.json and CI reference
 * only this neutral entry point — never a module command name directly.
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
        $generators = array_values(array_map(strval(...), (array) Config::get('telegram.modules_page_generators', [])));

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
}
