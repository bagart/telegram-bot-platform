<?php

/**
 * Tests for the prod-lock tip-freshness control: the cmd/deps/lock-tip.php
 * comparator driven through fixtures, plus the cmd/deps/check invocations that
 * register lock-tip-fresh:prod (mode parsing prerequisite and --offline skip).
 *
 * Pattern: tests/Unit/Baseline/EngineSelfcheckTest.php — shell controls driven
 * through Symfony Process from the repository root.
 */

use Symfony\Component\Process\Process;

function depsLockTipRoot(): string
{
    return dirname(__DIR__, 3);
}

function depsLockTipFixtures(): string
{
    return depsLockTipRoot().'/tests/fixtures/deps';
}

/**
 * Run a shell command from the repository root under the shared control timeout.
 */
function depsLockTipRun(string $command): Process
{
    $process = Process::fromShellCommandline($command, depsLockTipRoot());
    $process->setTimeout(120);
    $process->run();

    return $process;
}

function depsLockTipCompare(string $lock, ?string $tips = null): Process
{
    $command = 'php cmd/deps/lock-tip.php --lock '.escapeshellarg(depsLockTipFixtures().'/'.$lock);
    if ($tips !== null) {
        $command .= ' --tips '.escapeshellarg(depsLockTipFixtures().'/'.$tips);
    }

    return depsLockTipRun($command);
}

it('passes a fresh lock against injected tips', function () {
    $result = depsLockTipCompare('fresh.lock', 'tips-fresh.json');

    expect($result->getExitCode())->toBe(0, $result->getOutput().$result->getErrorOutput())
        ->and($result->getOutput())->toContain('summary: fresh=3 stale=0 hijack=0 skipped=0');
});

it('fails a stale lock with the remediation command', function () {
    $result = depsLockTipCompare('stale.lock', 'tips-stale.json');

    expect($result->getExitCode())->toBe(1, $result->getOutput().$result->getErrorOutput())
        ->and($result->getOutput())
        ->toContain('stale-lock-tip:bagart/ask-client:')
        ->toContain('run: bash cmd/deps/update --mode=both && bash cmd/deps/check');
});

it('flags a stable-tag pin as a policy failure', function () {
    $result = depsLockTipCompare('hijacked.lock', 'tips-fresh.json');

    expect($result->getExitCode())->toBe(5, $result->getOutput().$result->getErrorOutput())
        ->and($result->getOutput())->toContain('stable-tag-pin:bagart/tgbot-module-proxy: v0.1.1');
});

it('skips packages absent from the tips map instead of failing', function () {
    $result = depsLockTipCompare('fresh.lock', 'tips-missing.json');

    expect($result->getExitCode())->toBe(0, $result->getOutput().$result->getErrorOutput())
        ->and($result->getOutput())
        ->toContain('skipped:bagart/ask-client:')
        ->toContain('summary: fresh=0 stale=0 hijack=0 skipped=3');
});

it('skips an unreachable origin instead of failing', function () {
    $result = depsLockTipCompare('unreachable.lock');

    expect($result->getExitCode())->toBe(0, $result->getOutput().$result->getErrorOutput())
        ->and($result->getOutput())->toContain('skipped:bagart/unreachable-lib: ls-remote failed');
});

it('parses --mode=prod in cmd/deps/check (prerequisite A regression)', function () {
    $result = depsLockTipRun('bash cmd/deps/check --mode=prod --format=json');

    expect($result->getExitCode())->not->toBe(2)
        ->and($result->getErrorOutput())->not->toContain('Unknown option')
        ->and($result->getOutput())->toContain('"control":"lock-tip-fresh:prod"');
});

it('skips lock-tip-fresh:prod under --offline without probing origins', function () {
    $result = depsLockTipRun('bash cmd/deps/check --offline --format=json');

    expect($result->getOutput())
        ->toContain('"control":"lock-tip-fresh:prod","status":"skipped"')
        ->toContain('--offline: origin tips not queried');
});
