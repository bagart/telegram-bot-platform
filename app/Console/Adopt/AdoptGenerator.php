<?php

declare(strict_types=1);

namespace App\Console\Adopt;

use BAGArt\TelegramBot\Modules\TgModuleContract;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/**
 * GENERATE half of `modules:adopt` — the safe host artifacts only
 * (config/tg_modules.php entry + host Inertia page wrapper).
 *
 * Refuse-overwrite follows the MenuScaffoldCommand heredoc pattern: an
 * artifact that is already present is reported, never rewritten.
 */
final class AdoptGenerator
{
    private const CONFIG_MARKER = "'modules' => [";

    public function __construct(private readonly AdoptRepository $repository)
    {
    }

    /**
     * @return list<AdoptWrite>
     */
    public function plan(AdoptModule $module): array
    {
        return [
            $this->planConfigEntry($module),
            $this->planPageWrapper($module),
        ];
    }

    /** @param  list<AdoptWrite>  $writes */
    public function apply(array $writes): void
    {
        foreach ($writes as $write) {
            if ($write->alreadyPresent) {
                continue;
            }

            $this->repository->write($write->path, $write->contents);
        }
    }

    private function planConfigEntry(AdoptModule $module): AdoptWrite
    {
        $relative = 'config/tg_modules.php';
        $current = $this->repository->exists($relative) ? $this->repository->read($relative) : '';

        $alreadyPresent = preg_match(
            "/^\s*'".preg_quote($module->id, '/')."'\\s*=>\\s*new TgModuleConfig\\(/m",
            $current,
        ) === 1;

        if ($alreadyPresent) {
            return new AdoptWrite($relative, $this->configEntry($module), true);
        }

        $offset = strpos($current, self::CONFIG_MARKER);

        if ($offset === false) {
            throw new RuntimeException('config/tg_modules.php: no "modules" array marker found');
        }

        $lineEnd = strpos($current, "\n", $offset);

        if ($lineEnd === false) {
            throw new RuntimeException('config/tg_modules.php: unterminated "modules" array marker');
        }

        $insertAt = $lineEnd + 1;
        $merged = substr($current, 0, $insertAt).$this->configEntry($module).substr($current, $insertAt);

        return new AdoptWrite($relative, $merged, false);
    }

    private function planPageWrapper(AdoptModule $module): AdoptWrite
    {
        $component = $this->studly($module->id);
        $relative = sprintf('resources/js/pages/Settings/%s.tsx', $component);
        $exists = $this->repository->exists($relative);

        $contents = implode("\n", [
            "import type { ModuleSettingsShowProps } from '@/pages/settings/modules/show';",
            "import Show from '@/pages/settings/modules/show';",
            '',
            sprintf('export default function %sSettings(props: ModuleSettingsShowProps) {', $component),
            '    return <Show {...props} />;',
            '}',
            '',
        ]);

        return new AdoptWrite($relative, $contents, $exists);
    }

    private function configEntry(AdoptModule $module): string
    {
        $defaultEnabled = true;

        if (class_exists($module->provider) && is_a($module->provider, TgModuleContract::class, true)) {
            try {
                $defaultEnabled = $module->provider::descriptor()->defaultEnabled;
            } catch (Throwable) {
                $defaultEnabled = true;
            }
        }

        $lines = [
            sprintf("        '%s' => new TgModuleConfig(", $module->id),
            sprintf("            enabled: env('TG_MODULE_ENABLED_%s', %s),", $module->id, $defaultEnabled ? 'true' : 'false'),
            sprintf('            provider: %s::class,', $module->provider),
        ];

        $laravelProvider = $this->laravelProvider($module);

        if ($laravelProvider !== null) {
            $lines[] = sprintf('            laravelProvider: %s::class,', $laravelProvider);
        }

        foreach ($this->artisanCommands($module) as $command) {
            $commands[] = sprintf('                %s::class,', $command);
        }

        if (isset($commands)) {
            $lines[] = '            commands: [';
            foreach ($commands as $commandLine) {
                $lines[] = $commandLine;
            }
            $lines[] = '            ],';
        }

        if ($this->repository->exists(sprintf('misc/BAGArt/%s/resources/js/pages', $module->dir))) {
            $lines[] = '            frontendPages: [';
            $lines[] = sprintf("                base_path('misc/BAGArt/%s/resources/js/pages'),", $module->dir);
            $lines[] = '            ],';
        }

        $lines[] = sprintf("            sourcePath: base_path('misc/BAGArt/%s'),", $module->dir);
        $lines[] = '        ),';

        return implode("\n", $lines)."\n";
    }

    private function laravelProvider(AdoptModule $module): ?string
    {
        $candidates = glob($this->repository->path(sprintf('misc/BAGArt/%s/src/*ServiceProvider.php', $module->dir))) ?: [];

        foreach ($candidates as $candidate) {
            $fqcn = $module->namespace.'\\'.basename($candidate, '.php');

            if (class_exists($fqcn)) {
                return $fqcn;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function artisanCommands(AdoptModule $module): array
    {
        $commands = [];

        foreach (['Commands', 'Console'] as $subdirectory) {
            $candidates = glob($this->repository->path(sprintf('misc/BAGArt/%s/src/%s/*Command.php', $module->dir, $subdirectory))) ?: [];

            foreach ($candidates as $candidate) {
                $fqcn = $module->namespace.'\\'.$subdirectory.'\\'.basename($candidate, '.php');

                if (class_exists($fqcn) && is_a($fqcn, Command::class, true)) {
                    $commands[] = $fqcn;
                }
            }
        }

        return $commands;
    }

    private function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_', '.'], ' ', $value)));
    }
}
