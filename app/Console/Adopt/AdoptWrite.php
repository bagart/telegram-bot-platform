<?php

declare(strict_types=1);

namespace App\Console\Adopt;

/**
 * One planned GENERATE write: a host artifact `modules:adopt` can produce
 * without touching composer, locks or baselines (Q11's generate set of 4).
 */
final readonly class AdoptWrite
{
    public function __construct(
        /** path relative to the repository root */
        public string $path,
        public string $contents,
        /** true when the artifact already exists — GENERATE refuses to overwrite */
        public bool $alreadyPresent,
    ) {
    }
}
