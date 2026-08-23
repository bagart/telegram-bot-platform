<?php

declare(strict_types=1);

/**
 * Fixture-corpus runner (11-implementation-and-rollout.md §81–§85).
 *
 * tests/fixtures/baseline/corpus.json declares every baseline-tool case with
 * its fixture files and the expected exit code. Every scanner regression must
 * land here as a new case before the fix.
 */
use Symfony\Component\Process\Process;

function corpusRoot(): string
{
    return dirname(__DIR__, 3); // tests/Unit/Baseline -> repository root
}

function corpusRun(string $command): Process
{
    $process = Process::fromShellCommandline($command, corpusRoot());
    $process->setTimeout(60);
    $process->run();

    return $process;
}

it('passes the whole baseline fixture corpus', function () {
    $corpusPath = corpusRoot().'/tests/fixtures/baseline/corpus.json';
    $corpus = json_decode((string) file_get_contents($corpusPath), true);
    expect($corpus)->toBeArray('malformed corpus.json')
        ->and($corpus['cases'] ?? null)->toBeArray('corpus.json has no cases');

    $failures = [];
    foreach ($corpus['cases'] as $case) {
        $command = isset($case['file'])
            ? 'php tools/baseline/'.$case['tool'].' '.escapeshellarg(corpusRoot().'/'.$case['file'])
            : 'php tools/baseline/'.$case['tool'].' '.$case['args'];

        $result = corpusRun($command);
        if ((int) $result->getExitCode() !== (int) $case['expect_exit']) {
            $failures[] = sprintf(
                '%s: expected exit %d, got %d [%s]',
                $case['name'],
                $case['expect_exit'],
                $result->getExitCode(),
                trim(substr($result->getErrorOutput().$result->getOutput(), 0, 200)),
            );
        }
    }

    expect($failures)->toBe([]);
});
