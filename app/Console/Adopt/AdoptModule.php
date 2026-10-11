<?php

declare(strict_types=1);

namespace App\Console\Adopt;

/**
 * Identity of the module being adopted: everything the VALIDATE half needs to
 * address the package on disk (dir) and in both manifests (namespace).
 */
final readonly class AdoptModule
{
    public function __construct(
        /** descriptor id == config key */
        public string $id,
        /** package directory name under misc/BAGArt */
        public string $dir,
        /** PSR-4 prefix the package autoloads its src/ from */
        public string $namespace,
        /** FQCN of the TgModuleContract implementation */
        public string $provider,
    ) {
    }
}
