<?php

function configSecurityPayload(string $name, string $env, array $config): string
{
    $dir = dirname(__DIR__, 3).'/storage/framework/testing/baseline/config-security';

    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $path = $dir.'/'.$name.'.json';
    file_put_contents($path, json_encode(['env' => $env, 'config' => $config]));

    return $path;
}

function runConfigSecurity(string $payload): array
{
    $command = [
        PHP_BINARY,
        dirname(__DIR__, 3).'/tools/baseline/config-security.php',
        '--format=json',
        '--config-json='.$payload,
    ];

    $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptor, $pipes, dirname(__DIR__, 3));
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start config-security');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);

    return ['code' => $code, 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
}

function configSecuritySafeValues(): array
{
    return [
        'app.debug' => false,
        'app.key' => 'base64:test-key-value',
        'session.http_only' => true,
        'session.secure' => true,
        'session.same_site' => 'lax',
        'cors.supports_credentials' => false,
        'cors.allowed_origins' => ['https://example.com'],
    ];
}

it('passes with hardened production configuration', function () {
    $payload = configSecurityPayload('prod-ok', 'production', configSecuritySafeValues());

    $result = runConfigSecurity($payload);
    $data = json_decode($result['stdout'], true);

    expect($result['code'])->toBe(0)
        ->and($data['violations'])->toBe(0)
        ->and($data['env'])->toBe('production');
});

it('blocks insecure settings in production', function () {
    $values = array_merge(configSecuritySafeValues(), [
        'app.debug' => true,
        'session.http_only' => false,
        'session.same_site' => null,
    ]);
    $payload = configSecurityPayload('prod-bad', 'production', $values);

    $result = runConfigSecurity($payload);
    $data = json_decode($result['stdout'], true);

    expect($result['code'])->toBe(1)
        ->and($data['violations'])->toBe(3)
        ->and(collect($data['results'])->pluck('check')->all())->toContain('app.debug', 'session.http_only', 'session.same_site');
});

it('blocks a missing application key in production', function () {
    $values = array_merge(configSecuritySafeValues(), ['app.key' => null]);
    $payload = configSecurityPayload('prod-no-key', 'production', $values);

    $result = runConfigSecurity($payload);

    expect($result['code'])->toBe(1)
        ->and($result['stdout'])->toContain('application key must be set');
});

it('blocks wildcard CORS origins combined with credentials in production', function () {
    $values = array_merge(configSecuritySafeValues(), [
        'cors.supports_credentials' => true,
        'cors.allowed_origins' => ['*'],
    ]);
    $payload = configSecurityPayload('prod-cors', 'production', $values);

    $result = runConfigSecurity($payload);

    expect($result['code'])->toBe(1)
        ->and($result['stdout'])->toContain('wildcard origin with credentials');
});

it('degrades violations to warnings outside production', function () {
    $values = array_merge(configSecuritySafeValues(), ['app.debug' => true, 'session.secure' => false]);
    $payload = configSecurityPayload('dev-report', 'local', $values);

    $result = runConfigSecurity($payload);
    $data = json_decode($result['stdout'], true);

    expect($result['code'])->toBe(0)
        ->and($data['violations'])->toBe(0)
        ->and($data['warnings'])->toBe(2);
});
