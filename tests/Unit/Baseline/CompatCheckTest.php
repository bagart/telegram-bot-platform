<?php

function compatFixtureDir(): string
{
    $dir = dirname(__DIR__, 3).'/storage/framework/testing/baseline/compat';

    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function compatFixtureLib(string $name, array $require, array $extra = []): string
{
    $dir = compatFixtureDir().'/'.$name;

    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($dir.'/composer.json', json_encode(['require' => $require] + ($extra !== [] ? ['extra' => $extra] : [])));

    return 'storage/framework/testing/baseline/compat/'.$name;
}

function compatMatrixFile(string $name, array $entries, array $root = []): string
{
    $path = compatFixtureDir().'/'.$name.'.json';
    file_put_contents($path, json_encode(['entries' => $entries] + $root));

    return $path;
}

function runCompatCheck(string $matrixPath): array
{
    $command = [
        PHP_BINARY,
        dirname(__DIR__, 3).'/tools/baseline/compat-check.php',
        '--format=json',
        '--matrix='.$matrixPath,
    ];

    $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptor, $pipes, dirname(__DIR__, 3));
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start compat-check');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);

    return ['code' => $code, 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
}

it('passes when the runtime and matrix satisfy composer constraints', function () {
    $lib = compatFixtureLib('valid', ['php' => '>=8.2']);
    $matrix = compatMatrixFile('valid', [
        ['name' => 'valid', 'path' => $lib, 'php' => [PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION], 'ext' => []],
    ]);

    $result = runCompatCheck($matrix);

    expect($result['code'])->toBe(0)
        ->and(json_decode($result['stdout'], true)['violations'])->toBe(0);
});

it('reports matrix versions that violate the composer constraint', function () {
    $lib = compatFixtureLib('drift-php', ['php' => '^8.5']);
    $matrix = compatMatrixFile('drift-php', [
        ['name' => 'drift-php', 'path' => $lib, 'php' => ['8.4'], 'ext' => []],
    ]);

    $result = runCompatCheck($matrix);

    expect($result['code'])->toBe(1)
        ->and($result['stderr'])->toContain('matrix version 8.4 does not satisfy');
});

it('reports extension drift between composer and the matrix', function () {
    $lib = compatFixtureLib('drift-ext', ['php' => '>=8.2', 'ext-json' => '*']);
    $matrix = compatMatrixFile('drift-ext', [
        ['name' => 'drift-ext', 'path' => $lib, 'php' => ['8.5'], 'ext' => []],
    ]);

    $result = runCompatCheck($matrix);

    expect($result['code'])->toBe(1)
        ->and($result['stderr'])->toContain('ext drift');
});

it('reports unloaded required extensions', function () {
    $lib = compatFixtureLib('missing-ext', ['php' => '>=8.2', 'ext-does-not-exist-xyz' => '*']);
    $matrix = compatMatrixFile('missing-ext', [
        ['name' => 'missing-ext', 'path' => $lib, 'php' => ['8.5'], 'ext' => ['does-not-exist-xyz']],
    ]);

    $result = runCompatCheck($matrix);

    expect($result['code'])->toBe(1)
        ->and($result['stderr'])->toContain('ext-does-not-exist-xyz is not loaded');
});

it('reports a missing composer.json', function () {
    $matrix = compatMatrixFile('no-lib', [
        ['name' => 'no-lib', 'path' => 'storage/framework/testing/baseline/compat/does-not-exist', 'php' => ['8.5'], 'ext' => []],
    ]);

    $result = runCompatCheck($matrix);

    expect($result['code'])->toBe(1)
        ->and($result['stderr'])->toContain('composer.json not found');
});

it('passes when botApi declarations agree across entries and with composer extra', function () {
    $lib = compatFixtureLib('botapi-ok', ['php' => '>=8.2'], ['bot-api' => ['version' => '9.5']]);
    $consumer = compatFixtureLib('botapi-consumer', ['php' => '>=8.2']);
    $matrix = compatMatrixFile('botapi-ok', [
        ['name' => 'lib', 'path' => $lib, 'php' => [PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION], 'ext' => [], 'botApi' => '9.5'],
        ['name' => 'app', 'path' => $consumer, 'php' => [PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION], 'ext' => [], 'botApi' => '9.5'],
    ]);

    $result = runCompatCheck($matrix);

    expect($result['code'])->toBe(0)
        ->and(json_decode($result['stdout'], true)['violations'])->toBe(0);
});

it('reports botApi disagreement between entries', function () {
    $libA = compatFixtureLib('botapi-a', ['php' => '>=8.2']);
    $libB = compatFixtureLib('botapi-b', ['php' => '>=8.2']);
    $matrix = compatMatrixFile('botapi-drift', [
        ['name' => 'a', 'path' => $libA, 'php' => [PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION], 'ext' => [], 'botApi' => '9.5'],
        ['name' => 'b', 'path' => $libB, 'php' => [PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION], 'ext' => [], 'botApi' => '10.2'],
    ]);

    $result = runCompatCheck($matrix);

    expect($result['code'])->toBe(1)
        ->and($result['stderr'])->toContain('botApi disagreement')
        ->and($result['stderr'])->toContain('a=9.5, b=10.2');
});

it('reports drift between the matrix botApi and the component composer extra', function () {
    $lib = compatFixtureLib('botapi-extra-drift', ['php' => '>=8.2'], ['bot-api' => ['version' => '9.4']]);
    $matrix = compatMatrixFile('botapi-extra-drift', [
        ['name' => 'lib', 'path' => $lib, 'php' => [PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION], 'ext' => [], 'botApi' => '9.5'],
    ]);

    $result = runCompatCheck($matrix);

    expect($result['code'])->toBe(1)
        ->and($result['stderr'])->toContain('botApi drift: matrix declares 9.5, composer.json extra declares 9.4');
});

it('rejects a malformed botApi declaration', function () {
    $lib = compatFixtureLib('botapi-bad-format', ['php' => '>=8.2']);
    $matrix = compatMatrixFile('botapi-bad-format', [
        ['name' => 'lib', 'path' => $lib, 'php' => [PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION], 'ext' => [], 'botApi' => 'nine-five'],
    ]);

    $result = runCompatCheck($matrix);

    expect($result['code'])->toBe(1)
        ->and($result['stderr'])->toContain("invalid botApi 'nine-five'");
});

it('validates the shell contract against the current runtime', function () {
    $lib = compatFixtureLib('shell-runtime', ['php' => '>=8.2']);
    // The current runtime always satisfies its own OS family; an impossible
    // bash minimum must fail.
    $okMatrix = compatMatrixFile('shell-ok', [
        ['name' => 'app', 'path' => $lib, 'php' => [PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION], 'ext' => []],
    ], ['shell' => ['os' => [PHP_OS_FAMILY], 'bashMin' => '4.0']]);
    $badBash = compatMatrixFile('shell-bad-bash', [
        ['name' => 'app', 'path' => $lib, 'php' => [PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION], 'ext' => []],
    ], ['shell' => ['bashMin' => '99.0']]);

    $ok = runCompatCheck($okMatrix);
    $bad = runCompatCheck($badBash);

    expect($ok['code'])->toBe(0)
        ->and($bad['code'])->toBe(1)
        ->and($bad['stderr'])->toContain('bash')
        ->and($bad['stderr'])->toContain('99.0');
});

it('reports an undeclared OS family', function () {
    $lib = compatFixtureLib('shell-os', ['php' => '>=8.2']);
    $impossibleOs = PHP_OS_FAMILY === 'Linux' ? 'Darwin' : 'Linux';
    $matrix = compatMatrixFile('shell-bad-os', [
        ['name' => 'app', 'path' => $lib, 'php' => [PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION], 'ext' => []],
    ], ['shell' => ['os' => [$impossibleOs]]]);

    $result = runCompatCheck($matrix);

    expect($result['code'])->toBe(1)
        ->and($result['stderr'])->toContain("current OS family '".PHP_OS_FAMILY."' is not in the declared os list");
});
