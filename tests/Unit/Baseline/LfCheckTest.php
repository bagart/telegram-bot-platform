<?php

function lfFixtureDir(): string
{
    $dir = dirname(__DIR__, 3).'/storage/framework/testing/baseline';
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function lfFixture(string $name, string $contents): string
{
    $path = lfFixtureDir().'/'.$name;
    file_put_contents($path, $contents);

    return 'storage/framework/testing/baseline/'.$name;
}

function runLfCheck(string $tool, array $args): array
{
    $command = array_merge([
        PHP_BINARY,
        dirname(__DIR__, 3).'/tools/baseline/'.$tool,
    ], $args);

    $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptor, $pipes, dirname(__DIR__, 3));
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start baseline tool');
    }
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);

    return ['code' => $code, 'output' => (string) $output, 'error' => (string) $error];
}

it('reports CRLF files as violations', function () {
    $file = lfFixture('crlf.txt', "first\r\nsecond\r\n");

    $result = runLfCheck('lf-check.php', ['--paths='.$file, '--format=json']);

    expect($result['code'])->toBe(1)
        ->and(json_decode($result['output'], true)['violations'])->toBe([$file]);
});

it('fixes CRLF to LF in place', function () {
    $file = lfFixture('crlf-fix.txt', "first\r\nsecond\r\n");

    $result = runLfCheck('lf-check.php', ['--paths='.$file, '--fix']);

    expect($result['code'])->toBe(0)
        ->and(file_get_contents(dirname(__DIR__, 3).'/'.$file))->toBe("first\nsecond\n");
});

it('passes on LF files', function () {
    $file = lfFixture('lf.txt', "first\nsecond\n");

    expect(runLfCheck('lf-check.php', ['--paths='.$file])['code'])->toBe(0);
});
