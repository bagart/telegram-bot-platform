<?php

declare(strict_types=1);

namespace App\Console\Adopt;

use BAGArt\TelegramBot\Modules\TgModuleContract;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * Filesystem access for the artifacts `modules:adopt` reads and scaffolds.
 *
 * Every path is resolved against an injectable repository root (defaults to
 * the application base path), so assertions and GENERATE writes can be pointed
 * at a fixture tree from a test via app()->instance().
 */
final class AdoptRepository
{
    /**
     * Literal repository-relative paths this class is allowlisted to require
     * (03 §27: dynamic include/require of a variable path is forbidden; the
     * allowlist map is the only sanctioned entry point).
     *
     * @var list<string>
     */
    private const REQUIRE_ALLOWLIST = [
        'config/tg_modules.php',
    ];

    public function __construct(private readonly ?string $root = null)
    {
    }

    public function root(): string
    {
        return $this->root ?? base_path();
    }

    public function path(string $relative): string
    {
        return rtrim($this->root(), '/\\').'/'.ltrim($relative, '/\\');
    }

    public function exists(string $relative): bool
    {
        return file_exists($this->path($relative));
    }

    public function read(string $relative): string
    {
        $path = $this->path($relative);
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('unreadable file: %s', $relative));
        }

        return $contents;
    }

    public function write(string $relative, string $contents): void
    {
        $path = $this->path($relative);
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException(sprintf('cannot create directory: %s', $directory));
        }

        if (@file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('cannot write file: %s', $relative));
        }
    }

    /**
     * Decode a JSON document under the repository root.
     *
     * @return array<string, mixed>|null null when the file is absent or not an object
     */
    public function json(string $relative): ?array
    {
        if (! $this->exists($relative)) {
            return null;
        }

        $decoded = json_decode($this->read($relative), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Load config/tg_modules.php from the repository root. The file only
     * builds DTOs and calls env()/base_path(), both available in app context.
     *
     * @return array<string, mixed>
     */
    public function loadModuleConfig(): array
    {
        if (! is_file($this->moduleConfigPath())) {
            return [];
        }

        $raw = require self::moduleConfigPath();

        return is_array($raw) ? $raw : [];
    }

    /**
     * Resolve the sole allowlisted require target against the repository root.
     * Only literals from REQUIRE_ALLOWLIST are ever returned.
     */
    private function moduleConfigPath(): string
    {
        return $this->path(self::REQUIRE_ALLOWLIST[0]);
    }

    /**
     * Resolve a module id to its package identity.
     *
     * Order: the config entry's provider (fastest, works for modules not yet
     * discoverable from a fixture tree), then a scan of the package sources
     * under misc/BAGArt (needed before the config entry exists — GENERATE half).
     */
    public function resolveModule(string $id): ?AdoptModule
    {
        $config = $this->loadModuleConfig();
        $entry = $config['modules'][$id] ?? null;

        if (is_object($entry) && property_exists($entry, 'provider') && is_string($entry->provider)) {
            $module = $this->moduleFromProvider($id, $entry->provider);

            if ($module !== null) {
                return $module;
            }
        }

        foreach ($this->moduleCatalog() as $candidate) {
            if ($candidate->id === $id) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return list<AdoptModule> */
    public function moduleCatalog(): array
    {
        $catalog = [];
        $packages = glob($this->path('misc/BAGArt/*/composer.json')) ?: [];

        foreach ($packages as $composerPath) {
            $packageRoot = dirname($composerPath);
            $dir = basename($packageRoot);
            $composer = json_decode((string) @file_get_contents($composerPath), true);

            if (! is_array($composer)) {
                continue;
            }

            $psr4 = $composer['autoload']['psr-4'] ?? [];

            if (! is_array($psr4)) {
                continue;
            }

            foreach ($psr4 as $prefix => $relative) {
                if (! is_string($prefix) || ! is_string($relative)) {
                    continue;
                }

                if (! str_ends_with(rtrim($relative, '/'), 'src')) {
                    continue;
                }

                foreach (glob($packageRoot.'/'.rtrim($relative, '/').'/*Module.php') ?: [] as $moduleFile) {
                    $fqcn = rtrim($prefix, '\\').'\\'.basename($moduleFile, '.php');
                    $id = $this->descriptorIdOf($fqcn);

                    if ($id !== null) {
                        $catalog[] = new AdoptModule($id, $dir, rtrim($prefix, '\\'), $fqcn);
                    }
                }
            }
        }

        usort($catalog, static fn (AdoptModule $a, AdoptModule $b): int => $a->id <=> $b->id);

        return $catalog;
    }

    private function moduleFromProvider(string $id, string $provider): ?AdoptModule
    {
        $descriptorId = $this->descriptorIdOf($provider);

        if ($descriptorId === null || $descriptorId !== $id) {
            return null;
        }

        try {
            $fileName = (new ReflectionClass($provider))->getFileName();
        } catch (Throwable) {
            return null;
        }

        if (! is_string($fileName) || preg_match('#/misc/BAGArt/([^/]+)/src/#', $fileName, $matches) !== 1) {
            return null;
        }

        $namespace = (new ReflectionClass($provider))->getNamespaceName();
        $namespace = trim($namespace, '\\');

        if ($namespace === '') {
            return null;
        }

        return new AdoptModule($id, $matches[1], $namespace, $provider);
    }

    private function descriptorIdOf(string $fqcn): ?string
    {
        if (! class_exists($fqcn) || ! is_a($fqcn, TgModuleContract::class, true)) {
            return null;
        }

        try {
            return $fqcn::descriptor()->id;
        } catch (Throwable) {
            return null;
        }
    }
}
