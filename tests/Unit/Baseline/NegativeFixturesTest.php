<?php

/**
 * Baseline self-tests: negative fixtures (11-implementation-and-rollout.md §84).
 *
 * Deliberately broken inputs must be detected by the matching baseline tool.
 * Every case here is the regression suite for a scanner bypass.
 */

use Symfony\Component\Process\Process;

function baselineFixtureRoot(): string
{
    return dirname(__DIR__, 3);
}

function baselineFixtureRun(string $command): Process
{
    $process = Process::fromShellCommandline($command, baselineFixtureRoot());
    $process->setTimeout(60);
    $process->run();

    return $process;
}

it('detects a planted AWS secret', function () {
    $fixture = 'storage/framework/testing/baseline/negative-aws-secret.php';
    $abs = baselineFixtureRoot().'/'.$fixture;
    if (! is_dir(dirname($abs))) {
        mkdir(dirname($abs), 0777, true);
    }
    file_put_contents($abs, "<?php\nreturn ['key' => 'AKIAIOSFODNN7EXAMPLE'];\n");

    $result = baselineFixtureRun("php tools/baseline/secret-scan.php --paths={$fixture}");

    expect($result->getExitCode())->not->toBe(0, 'secret scanner missed a planted AWS key');
});

it('detects CRLF line endings', function () {
    $fixture = 'storage/framework/testing/baseline/negative-crlf.txt';
    $abs = baselineFixtureRoot().'/'.$fixture;
    if (! is_dir(dirname($abs))) {
        mkdir(dirname($abs), 0777, true);
    }
    file_put_contents($abs, "line one\r\nline two\r\n");

    $result = baselineFixtureRun("php tools/baseline/lf-check.php --paths={$fixture}");

    expect($result->getExitCode())->not->toBe(0, 'lf-check missed CRLF line endings');
});

it('rejects a non-conventional commit message', function () {
    $fixture = baselineFixtureRoot().'/storage/framework/testing/baseline/negative-commit-msg.txt';
    if (! is_dir(dirname($fixture))) {
        mkdir(dirname($fixture), 0777, true);
    }
    file_put_contents($fixture, "just some random message without prefix\n");

    $result = baselineFixtureRun('php tools/baseline/commit-msg.php '.escapeshellarg($fixture));

    expect($result->getExitCode())->not->toBe(0, 'commit-msg accepted a non-conventional message');
});

it('accepts a conventional commit message', function () {
    $fixture = baselineFixtureRoot().'/storage/framework/testing/baseline/positive-commit-msg.txt';
    if (! is_dir(dirname($fixture))) {
        mkdir(dirname($fixture), 0777, true);
    }
    file_put_contents($fixture, "feat: add negative fixture coverage\n");

    $result = baselineFixtureRun('php tools/baseline/commit-msg.php '.escapeshellarg($fixture));

    expect($result->getExitCode())->toBe(0, 'commit-msg rejected a valid conventional message');
});
