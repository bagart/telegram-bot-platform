<?php

/**
 * Baseline self-tests: distribution & drift tooling (11 §6–§7, §64–§66).
 *
 * Verifies the pack artifact is complete and the drift-report JSON contract
 * holds on a clean tree.
 */

use Symfony\Component\Process\Process;

function baselineDistRoot(): string
{
    return dirname(__DIR__, 3);
}

function baselineDistRun(string $command): Process
{
    $process = Process::fromShellCommandline($command, baselineDistRoot());
    $process->setTimeout(120);
    $process->run();

    return $process;
}

it('verifies the working tree against MANIFEST.json', function () {
    $result = baselineDistRun('php tools/baseline/manifest.php --verify');

    expect($result->getExitCode())->toBe(0, $result->getOutput().$result->getErrorOutput())
        ->and($result->getOutput())->toContain('in sync');
});

it('packs a distribution tarball with manifest and version', function () {
    $dist = baselineDistRun('bash cmd/baseline/pack');

    expect($dist->isSuccessful())->toBeTrue($dist->getErrorOutput());

    $version = trim((string) file_get_contents(baselineDistRoot().'/tools/baseline/VERSION'));
    $tarball = baselineDistRoot()."/dist/security-baseline-v{$version}.tar.gz";
    expect(is_file($tarball))->toBeTrue("tarball missing at {$tarball}")
        ->and(is_file($tarball.'.sha256'))->toBeTrue('checksum sidecar missing');

    // The archive must carry VERSION and MANIFEST.json at its root.
    $listing = baselineDistRun("tar -tzf ".escapeshellarg($tarball));
    expect($listing->isSuccessful())->toBeTrue()
        ->and($listing->getOutput())->toContain("security-baseline-v{$version}/VERSION")
        ->toContain("security-baseline-v{$version}/MANIFEST.json");
});

it('reports drift in json format when a tracked file changes', function () {
    $probe = baselineDistRoot().'/tools/baseline/VERSION';
    $original = (string) file_get_contents($probe);
    file_put_contents($probe, $original.'9');
    try {
        $result = baselineDistRun('php tools/baseline/manifest.php --verify --format=json');
        $payload = json_decode($result->getOutput(), true);
        expect($result->getExitCode())->toBe(1)
            ->and($payload['changed'])->toContain('tools/baseline/VERSION')
            ->and($payload['clean'])->toBeFalse();
    } finally {
        file_put_contents($probe, $original);
    }
});
