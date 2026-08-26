<?php

/**
 * Baseline self-tests: the control engine (11-implementation-and-rollout.md §82).
 *
 * Runs the engine self-check harness and asserts every verdict. The harness
 * (tools/baseline/selfcheck-engine.sh) exercises cycle detection, resume
 * journaling, per-control budgets, the max-jobs clamp and profile
 * composition in a real shell.
 */

use Symfony\Component\Process\Process;

function baselineEngineRoot(): string
{
    return dirname(__DIR__, 3);
}

function baselineEngineRun(string $command): Process
{
    $process = Process::fromShellCommandline($command, baselineEngineRoot());
    $process->setTimeout(120);
    $process->run();

    return $process;
}

it('passes the control engine self-check harness', function () {
    $result = baselineEngineRun('bash tools/baseline/selfcheck-engine.sh');

    $report = file_get_contents(baselineEngineRoot().'/.cache/baseline/selfcheck.txt');

    expect($result->isSuccessful())->toBeTrue("selfcheck harness failed:\n{$report}")
        ->and($report)->toContain('cycle-detect: OK')
        ->toContain('resume-journal: OK')
        ->toContain('budget-enforce: OK')
        ->toContain('maxjobs-clamp: OK')
        ->toContain('profiles-compose: OK')
        ->toContain('DONE');
});

it('reports control durations in JSON output', function () {
    $script = <<<'BASH'
        set -euo pipefail
        cd "%s"
        export REPO_ROOT="$PWD"
        source cmd/lib/common.sh
        source cmd/lib/output.sh
        source cmd/lib/contract.sh
        source cmd/lib/engine.sh
        FORMAT=json QUIET=1 RESUME=0 VERBOSE=0
        ctl_probe() { return 0; }
        engine_register probe "" ctl_probe
        engine_execute
        report_finish 0
        BASH;

    $path = baselineEngineRoot().'/storage/framework/testing/baseline/engine-json.sh';
    if (! is_dir(dirname((string) $path))) {
        mkdir(dirname((string) $path), 0777, true);
    }
    file_put_contents($path, sprintf($script, baselineEngineRoot()));

    $result = baselineEngineRun('bash '.escapeshellarg($path));

    expect($result->isSuccessful())->toBeTrue($result->getOutput().$result->getErrorOutput())
        ->and($result->getOutput())->toContain('"control":"probe"')
        ->toContain('"duration_ms":')
        ->toContain('"status":"passed"');
});
