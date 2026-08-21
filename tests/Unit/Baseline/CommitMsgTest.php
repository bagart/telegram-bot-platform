<?php

function commitMsgFixture(string $subject): string
{
    $dir = dirname(__DIR__, 3).'/storage/framework/testing/baseline';
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $path = $dir.'/'.bin2hex(random_bytes(4)).'-commit-msg.txt';
    file_put_contents($path, $subject.PHP_EOL.PHP_EOL.'# comment line'.PHP_EOL);

    return $path;
}

function runCommitMsg(string $tool, array $args): array
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

it('accepts every canonical commit prefix', function (string $prefix) {
    $path = commitMsgFixture($prefix.': do the thing');

    expect(runCommitMsg('commit-msg.php', [$path])['code'])->toBe(0);
})->with([
    'feat', 'fix', 'refactor', 'perf', 'test', 'docs', 'build', 'ci', 'chore', 'security',
]);

it('accepts scoped and breaking-change subjects', function (string $subject) {
    $path = commitMsgFixture($subject);

    expect(runCommitMsg('commit-msg.php', [$path])['code'])->toBe(0);
})->with([
    'feat(tg-webhook): validate secret before resolution',
    'fix!: correct lease renewal race',
]);

it('rejects messages without a canonical prefix and suggests a correction', function () {
    $path = commitMsgFixture('Security: harden dependency policy');

    $result = runCommitMsg('commit-msg.php', [$path]);

    expect($result['code'])->toBe(1)
        ->and($result['error'])->toContain('security: harden dependency policy');
});

it('allows merge and revert commits without prefixes', function (string $subject) {
    $path = commitMsgFixture($subject);

    expect(runCommitMsg('commit-msg.php', [$path])['code'])->toBe(0);
})->with([
    'Merge branch feature/x into main',
    'Revert "fix: something broken"',
]);
