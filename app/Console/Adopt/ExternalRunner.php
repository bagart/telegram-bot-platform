<?php

declare(strict_types=1);

namespace App\Console\Adopt;

/**
 * The external controls `modules:adopt` defers to instead of duplicating
 * them: `cmd/deps/check` (K20/K21 layout, lock and purity controls) and
 * `tg:modules:validate` (whole registry validation). Q11 keeps the adoption
 * command read-only with respect to composer, locks and baselines — it only
 * observes what those tools report.
 */
interface ExternalRunner
{
    /** False when a required tool (bash, composer) is missing — maps to exit code 3. */
    public function isAvailable(): bool;

    /**
     * Run `bash cmd/deps/check --offline --format=json` once and return its
     * results keyed by control id.
     *
     * @return array<string, array{status: string, message: string}>|null null when the tool produced no parseable payload
     */
    public function depsCheck(): ?array;

    /**
     * Run the engine's own registry validator.
     *
     * @return array{exit_code: int, output: string}
     */
    public function modulesValidate(): array;

    /** Port of cmd/deps/lib.sh prod_lock_is_pure() — true when the prod lock is pure. */
    public function prodLockIsPure(): bool;
}
