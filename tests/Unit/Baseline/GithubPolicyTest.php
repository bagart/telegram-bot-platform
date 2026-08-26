<?php

function githubPolicyFixtureDir(): string
{
    $dir = dirname(__DIR__, 3).'/storage/framework/testing/baseline/github-policy';

    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    return $dir;
}

function githubPolicyFixture(string $name, array $files): string
{
    $root = githubPolicyFixtureDir().'/'.$name;
    if (! is_dir($root)) {
        mkdir($root, 0777, true);
    }
    foreach ($files as $path => $content) {
        $full = $root.'/'.$path;
        $dir = dirname($full);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($full, $content);
    }

    return $root;
}

function githubPolicyWorkflow(array $overrides = [], array $withoutKeys = []): string
{
    $config = array_merge([
        'name' => 'fixture',
        'on' => ['push' => ['branches' => ['main']]],
        'permissions' => ['contents' => 'read'],
        'concurrency' => ['group' => 'fixture-${{ github.ref }}'],
        'jobs' => [
            'build' => [
                'runs-on' => 'ubuntu-latest',
                'steps' => [
                    ['uses' => 'actions/checkout@df4cb1c069e1874edd31b4311f1884172cec0e10 # v6.0.3'],
                ],
            ],
        ],
    ], $overrides);

    foreach ($withoutKeys as $key) {
        unset($config[$key]);
    }

    return Symfony\Component\Yaml\Yaml::dump($config, 6, 2);
}

function runGithubPolicy(string $root): array
{
    $command = [
        PHP_BINARY,
        dirname(__DIR__, 3).'/tools/baseline/github-policy.php',
        '--root='.$root,
        '--format=json',
    ];

    $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptor, $pipes, dirname(__DIR__, 3));
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start github-policy');
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);

    return ['code' => $code, 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
}

it('passes on a compliant workflow set with covering CODEOWNERS', function () {
    $root = githubPolicyFixture('valid', [
        '.github/workflows/ci.yml' => githubPolicyWorkflow(),
        '.github/CODEOWNERS' => "* @bagart\n",
    ]);

    $result = runGithubPolicy($root);

    expect($result['code'])->toBe(0)
        ->and(json_decode($result['stdout'], true)['violations'])->toBe(0);
});

it('reports a workflow without top-level permissions', function () {
    $root = githubPolicyFixture('no-permissions', [
        '.github/workflows/ci.yml' => githubPolicyWorkflow([], ['permissions']),
        '.github/CODEOWNERS' => "* @bagart\n",
    ]);

    $result = runGithubPolicy($root);

    expect($result['code'])->toBe(1)
        ->and($result['stdout'])->toContain('missing top-level permissions');
});

it('rejects write-all permissions', function () {
    $root = githubPolicyFixture('write-all', [
        '.github/workflows/ci.yml' => githubPolicyWorkflow(['permissions' => 'write-all']),
        '.github/CODEOWNERS' => "* @bagart\n",
    ]);

    $result = runGithubPolicy($root);

    expect($result['code'])->toBe(1)
        ->and($result['stdout'])->toContain('violates least privilege');
});

it('reports an action pinned by tag instead of SHA', function () {
    $workflow = str_replace(
        'actions/checkout@df4cb1c069e1874edd31b4311f1884172cec0e10 # v6.0.3',
        'actions/checkout@v6',
        githubPolicyWorkflow()
    );
    $root = githubPolicyFixture('tag-pinned', [
        '.github/workflows/ci.yml' => $workflow,
        '.github/CODEOWNERS' => "* @bagart\n",
    ]);

    $result = runGithubPolicy($root);

    expect($result['code'])->toBe(1)
        ->and($result['stdout'])->toContain('not pinned to a full commit SHA');
});

it('allows local composite action references', function () {
    $workflow = str_replace(
        'actions/checkout@df4cb1c069e1874edd31b4311f1884172cec0e10 # v6.0.3',
        './.github/actions/local',
        githubPolicyWorkflow()
    );
    $root = githubPolicyFixture('local-action', [
        '.github/workflows/ci.yml' => $workflow,
        '.github/CODEOWNERS' => "* @bagart\n",
    ]);

    $result = runGithubPolicy($root);

    expect($result['code'])->toBe(0);
});

it('requires a concurrency group on pull-request workflows', function () {
    $root = githubPolicyFixture('no-concurrency', [
        '.github/workflows/ci.yml' => githubPolicyWorkflow(['on' => ['pull_request_target' => ['types' => ['opened']]]], ['concurrency']),
        '.github/CODEOWNERS' => "* @bagart\n",
    ]);

    $result = runGithubPolicy($root);

    expect($result['code'])->toBe(1)
        ->and($result['stdout'])->toContain('without a concurrency group');
});

it('does not require concurrency on push-only workflows', function () {
    $root = githubPolicyFixture('push-only', [
        '.github/workflows/ci.yml' => githubPolicyWorkflow([], ['concurrency']),
        '.github/CODEOWNERS' => "* @bagart\n",
    ]);

    $result = runGithubPolicy($root);

    expect($result['code'])->toBe(0);
});

it('reports a missing CODEOWNERS file', function () {
    $root = githubPolicyFixture('no-codeowners', [
        '.github/workflows/ci.yml' => githubPolicyWorkflow(),
    ]);

    $result = runGithubPolicy($root);

    expect($result['code'])->toBe(1)
        ->and($result['stdout'])->toContain('CODEOWNERS is missing');
});

it('reports uncovered security-sensitive paths', function () {
    $root = githubPolicyFixture('uncovered', [
        '.github/workflows/ci.yml' => githubPolicyWorkflow(),
        '.github/CODEOWNERS' => "/app/ @bagart\n",
    ]);

    $result = runGithubPolicy($root);

    expect($result['code'])->toBe(1)
        ->and($result['stdout'])->toContain('no rule covering /.github')
        ->and($result['stdout'])->toContain('no rule covering /tools/baseline');
});

it('accepts explicit directory rules as coverage', function () {
    $root = githubPolicyFixture('dir-rules', [
        '.github/workflows/ci.yml' => githubPolicyWorkflow(),
        '.github/CODEOWNERS' => "/.github/ @bagart\n/tools/baseline/ @bagart\n",
    ]);

    $result = runGithubPolicy($root);

    expect($result['code'])->toBe(0);
});
