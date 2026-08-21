<?php

function baselineRepoRoot(): string
{
    return dirname(__DIR__, 3);
}

function baselineFixtureDir(): string
{
    $dir = baselineRepoRoot().'/storage/framework/testing/baseline';
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function baselineFixture(string $name, string $contents): string
{
    $path = baselineFixtureDir().'/'.$name;
    file_put_contents($path, $contents);

    return 'storage/framework/testing/baseline/'.$name;
}

function runSecretScan(string $tool, array $args): array
{
    $command = array_merge([
        PHP_BINARY,
        baselineRepoRoot().'/tools/baseline/'.$tool,
    ], $args);

    $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptor, $pipes, baselineRepoRoot());
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

it('detects a Telegram bot token as a secret', function () {
    $file = baselineFixture('tg-token.txt', 'token = "1234567890:AAF3cD_eFgHiJkLmNoPqRsTuVwXyZ123456"'.PHP_EOL);

    $result = runSecretScan('secret-scan.php', ['--paths='.$file, '--format=json']);

    expect($result['code'])->toBe(1)
        ->and($result['output'])->toContain('telegram-bot-token')
        ->and(json_decode($result['output'], true)['findings'][0]['rule'])->toBe('telegram-bot-token');
});

it('passes on files without secrets', function () {
    $file = baselineFixture('clean.txt', 'just ordinary config value = hello-world'.PHP_EOL);

    $result = runSecretScan('secret-scan.php', ['--paths='.$file, '--format=json']);

    expect($result['code'])->toBe(0)
        ->and(json_decode($result['output'], true)['findings'])->toBe([]);
});

it('masks detected secret excerpts in output', function () {
    $file = baselineFixture('aws-key.txt', 'key = AKIAIOSFODNN7EXAMPLE'.PHP_EOL);

    $result = runSecretScan('secret-scan.php', ['--paths='.$file]);

    expect($result['code'])->toBe(1)
        ->and($result['error'])->not->toContain('AKIAIOSFODNN7EXAMPLE');
});
