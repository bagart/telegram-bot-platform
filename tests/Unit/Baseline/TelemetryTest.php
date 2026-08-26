<?php

require_once dirname(__DIR__, 3).'/tools/baseline/telemetry.php';

it('aggregates runs, failures and durations per control', function () {
    $lines = [
        '{"ts":"2026-08-25T10:00:00Z","control":"lint","status":"passed","duration_ms":120}',
        '{"ts":"2026-08-25T10:05:00Z","control":"lint","status":"failed","duration_ms":300}',
        '{"ts":"2026-08-25T10:06:00Z","control":"lint","status":"skipped","duration_ms":0}',
        'garbage line',
        '{"control":"tests","status":"passed"}',
    ];

    $stats = telemetry_aggregate($lines);

    expect($stats['lint']['runs'])->toBe(3)
        ->and($stats['lint']['failures'])->toBe(1)
        ->and($stats['lint']['skipped'])->toBe(1)
        ->and($stats['lint']['failure_rate'])->toBe(round(1 / 3, 3))
        ->and($stats['lint']['avg_ms'])->toBe(140.0)
        ->and($stats['lint']['max_ms'])->toBe(300)
        ->and($stats['lint']['last_failure'])->toBe('2026-08-25T10:05:00Z')
        ->and($stats['tests']['runs'])->toBe(1)
        ->and($stats['tests']['avg_ms'])->toBe(0.0);
});

it('returns empty stats for missing or empty event logs', function () {
    expect(telemetry_aggregate([]))->toBe([])
        ->and(telemetry_aggregate(['', 'not json']))->toBe([]);
});
