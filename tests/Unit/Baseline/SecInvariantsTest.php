<?php

require_once dirname(__DIR__, 3).'/tools/baseline/sec-invariants.php';

function secInvariantsInput(array $overrides = []): array
{
    $workflows = [
        'security.yml' => "name: security\non:\n  push:\njobs:\n  scan:\n    steps:\n      - uses: actions/checkout@abc\n      - run: php tools/baseline/secret-scan.php --all\n",
        'nightly.yml' => "name: nightly\non:\n  schedule:\njobs:\n  history:\n    steps:\n      - uses: actions/checkout@abc\n        with:\n          fetch-depth: 0\n      - uses: gitleaks/gitleaks-action@def\n",
    ];

    return array_merge([
        'workflows' => $workflows,
        'toolVersions' => [
            '_comment' => 'note',
            'php' => ['min' => '8.5'],
            'semgrep' => ['pinned' => '1.86.0'],
        ],
        'allowlist' => [
            ['rule' => 'telegram-bot-token', 'path' => 'tests/*', 'reason' => 'synthetic', 'expires' => '2099-12-31'],
        ],
        'requiredPolicyFiles' => [
            'composer.lock' => true,
            '.github/workflows/dependency-review.yml' => true,
            'tools/baseline/yaml-lint.php' => true,
            'tools/baseline/secret-allowlist.json' => true,
            'tools/baseline/composer-plugin-allowlist.json' => true,
            'tools/baseline/composer-scripts-baseline.json' => true,
        ],
        'profilesScript' => "# detection\nPROFILES=()\n",
        'configSecuritySource' => "<?php --profile gate\n",
        'githubPolicySource' => "<?php const SHA_PATTERN = '/^[a-f0-9]{40}$/';\n",
    ], $overrides);
}

function runSecInvariants(): array
{
    $command = [
        PHP_BINARY,
        dirname(__DIR__, 3).'/tools/baseline/sec-invariants.php',
        '--format=json',
    ];

    $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptor, $pipes, dirname(__DIR__, 3));
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start sec-invariants');
    }

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    proc_close($process);

    return ['stdout' => (string) $stdout];
}

it('passes with fully wired security invariants', function () {
    $results = sec_invariants_evaluate(secInvariantsInput());

    $failed = array_filter($results, fn (array $r) => $r['status'] === 'fail');

    expect($failed)->toBe([]);
});

it('fails SEC-01 when no workflow runs a secret scanner', function () {
    $input = secInvariantsInput(['workflows' => ['ci.yml' => "on:\n  push:\njobs:\n  build:\n    steps:\n      - run: echo hi\n"]]);

    $results = sec_invariants_evaluate($input);
    $sec01 = current(array_filter($results, fn (array $r) => $r['id'] === 'SEC-01'));

    expect($sec01['status'])->toBe('fail');
});

it('fails SEC-03 only when the mandatory secret scans run silently', function () {
    // Step-level continue-on-error around gitleaks.
    $silent = ['nightly.yml' => "name: nightly\non:\n  schedule:\njobs:\n  scan:\n    steps:\n      - uses: gitleaks/gitleaks-action@def\n        continue-on-error: true\n"];
    $results = sec_invariants_evaluate(secInvariantsInput(['workflows' => $silent]));
    $sec03 = current(array_filter($results, fn (array $r) => $r['id'] === 'SEC-03'));
    expect($sec03['status'])->toBe('fail');

    // Job-level continue-on-error silencing scanner steps.
    $jobLevel = ['nightly.yml' => "name: nightly\non:\n  schedule:\njobs:\n  scan:\n    continue-on-error: true\n    steps:\n      - uses: gitleaks/gitleaks-action@def\n"];
    $results = sec_invariants_evaluate(secInvariantsInput(['workflows' => $jobLevel]));
    $sec03 = current(array_filter($results, fn (array $r) => $r['id'] === 'SEC-03'));
    expect($sec03['status'])->toBe('fail');

    // continue-on-error on an unrelated job must not trip the invariant.
    $unrelated = ['nightly.yml' => "name: nightly\non:\n  schedule:\njobs:\n  coverage:\n    continue-on-error: true\n    steps:\n      - run: vendor/bin/pest --coverage\n  history:\n    steps:\n      - uses: gitleaks/gitleaks-action@def\n"];
    $results = sec_invariants_evaluate(secInvariantsInput(['workflows' => $unrelated]));
    $sec03 = current(array_filter($results, fn (array $r) => $r['id'] === 'SEC-03'));
    expect($sec03['status'])->toBe('pass');

    // Staged report-mode SAST stays out of scope until A2 promotion.
    $stagedSast = ['security.yml' => "name: security\non:\n  push:\njobs:\n  sec:\n    steps:\n      - name: SAST (report mode)\n        continue-on-error: true\n        run: semgrep\n      - name: Secret scan\n        run: php tools/baseline/secret-scan.php --all\n"];
    $results = sec_invariants_evaluate(secInvariantsInput(['workflows' => $stagedSast]));
    $sec03 = current(array_filter($results, fn (array $r) => $r['id'] === 'SEC-03'));
    expect($sec03['status'])->toBe('pass');
});

it('rejects allowlist entries without reason or with expired expiry (SEC-06)', function () {
    $missingReason = secInvariantsInput([
        'allowlist' => [['rule' => 'r', 'path' => 'tests/*', 'expires' => '2099-01-01']],
    ]);
    $results = sec_invariants_evaluate($missingReason);
    expect(current(array_filter($results, fn (array $r) => $r['id'] === 'SEC-06'))['status'])->toBe('fail');

    $expired = secInvariantsInput([
        'allowlist' => [['rule' => 'r', 'path' => 'tests/*', 'reason' => 'ok', 'expires' => '2020-01-01']],
    ]);
    $results = sec_invariants_evaluate($expired);
    expect(current(array_filter($results, fn (array $r) => $r['id'] === 'SEC-06'))['status'])->toBe('fail');
});

it('rejects globally scoped exception paths (SEC-07)', function () {
    $input = secInvariantsInput([
        'allowlist' => [['rule' => 'r', 'path' => '*', 'reason' => 'too broad', 'expires' => '2099-01-01']],
    ]);

    $results = sec_invariants_evaluate($input);
    $sec07 = current(array_filter($results, fn (array $r) => $r['id'] === 'SEC-07'));

    expect($sec07['status'])->toBe('fail');
});

it('requires every tool version entry to pin or floor a version (SEC-10)', function () {
    $input = secInvariantsInput([
        'toolVersions' => ['semgrep' => ['note' => 'no version at all']],
    ]);

    $results = sec_invariants_evaluate($input);
    $sec10 = current(array_filter($results, fn (array $r) => $r['id'] === 'SEC-10'));

    expect($sec10['status'])->toBe('fail');
});

it('holds against the live repository state', function () {
    // The CLI tool exits 0 only when all machine-checkable invariants hold;
    // this keeps the audit honest if the repo wiring drifts.
    $result = runSecInvariants();
    $data = json_decode($result['stdout'], true);
    $failedIds = array_map(
        fn (array $r) => $r['id'],
        array_filter($data['results'] ?? [], fn (array $r) => $r['status'] === 'fail'),
    );

    expect($failedIds)->toBe([]);
});
