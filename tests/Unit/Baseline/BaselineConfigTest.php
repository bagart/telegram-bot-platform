<?php

require_once dirname(__DIR__, 3).'/tools/baseline/baseline-config.php';

it('deep-merges overrides over the base config', function () {
    $base = [
        'thresholds' => ['coverage_min' => null, 'mutation' => ['msi_floor' => 0.0, 'covered_code_msi_floor' => 0.0]],
        'levels' => ['quick' => ['controls' => ['line-endings', 'secret-scan']]],
    ];
    $overrides = [
        'thresholds' => ['mutation' => ['msi_floor' => 55.0]],
        'levels' => ['quick' => ['controls' => ['line-endings', 'secret-scan', 'deps-check']]],
    ];

    $merged = baseline_config_merge($base, $overrides);

    expect($merged['thresholds']['coverage_min'])->toBeNull()
        ->and($merged['thresholds']['mutation']['msi_floor'])->toBe(55.0)
        ->and($merged['thresholds']['mutation']['covered_code_msi_floor'])->toBe(0.0)
        ->and($merged['levels']['quick']['controls'])->toBe(['line-endings', 'secret-scan', 'deps-check']);
});

it('rejects overrides outside the allowlist but accepts listed ones', function () {
    $ok = baseline_config_forbidden([
        'thresholds' => ['coverage_min' => 60],
        'budgets' => ['test_suites' => ['Tests\\Feature' => 120]],
    ]);
    expect($ok)->toBe([]);

    $bad = baseline_config_forbidden([
        'security' => ['secret_scan' => false],
        'thresholds' => ['coverage_min' => 60, 'custom' => 1],
    ]);

    expect($bad)->toBe(['security.secret_scan', 'thresholds.custom']);
});

it('treats list values as atomic overrides', function () {
    $base = ['levels' => ['quick' => ['controls' => ['a']]]];

    $merged = baseline_config_merge($base, ['levels' => ['quick' => ['controls' => ['b', 'c']]]]);

    expect($merged['levels']['quick']['controls'])->toBe(['b', 'c']);
});

it('composes effective config with enforcement flags derived from floors', function () {
    $config = baseline_config_compose([
        'profiles' => ['laravel', 'telegram'],
        'toolVersions' => ['php' => ['min' => '8.5']],
        'testBudgets' => ['Tests\\Unit' => 60],
        'mutationFloors' => ['libraries' => ['bagart/async-kernel' => ['msi_floor' => 65.0, 'covered_code_msi_floor' => 70.0]]],
        'coverageMin' => '55',
        'overrides' => [],
    ]);

    expect($config['profiles']['detected'])->toBe(['laravel', 'telegram'])
        ->and($config['thresholds']['coverage_min'])->toBe(55.0)
        ->and($config['thresholds']['mutation']['enforced'])->toBeTrue()
        ->and($config['thresholds']['mutation']['msi_floor'])->toBe(65.0);
});
