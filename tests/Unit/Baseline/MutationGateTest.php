<?php

require_once dirname(__DIR__, 3).'/tools/baseline/mutation-gate.php';

it('resolves the floor and enforcement flag for a configured library', function () {
    $baseline = ['libraries' => ['bagart/async-kernel' => ['msi_floor' => 65]]];

    $result = mutation_gate_resolve($baseline, 'bagart/async-kernel');

    expect($result['min_score'])->toBe(65.0)
        ->and($result['enforced'])->toBeTrue();
});

it('stays report-only while the floor is zero', function () {
    $result = mutation_gate_resolve(['libraries' => ['bagart/async-kernel' => ['msi_floor' => 0]]], 'bagart/async-kernel');

    expect($result['min_score'])->toBe(0.0)
        ->and($result['enforced'])->toBeFalse();
});

it('treats an unknown library as report-only with zero floor', function () {
    $result = mutation_gate_resolve(['libraries' => []], 'bagart/unknown');

    expect($result['min_score'])->toBe(0.0)
        ->and($result['enforced'])->toBeFalse();
});

it('reads the shipped baseline and prints the floor via CLI', function () {
    $path = dirname(__DIR__, 3).'/tools/baseline/mutation-baseline.json';
    $baseline = mutation_gate_load_json($path);

    expect($baseline['libraries']['bagart/async-kernel']['msi_floor'])->toBeInt();
});
