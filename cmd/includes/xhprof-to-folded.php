#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/xhprof-lib.php';

$usage = <<<USAGE
Usage: php xhprof-to-folded.php [--metric=wt|cpu|mu|pmu|ct] <file.xhprof>

Converts xhprof profile to folded stack format for flamegraph.pl.
Outputs to stdout.

Metrics:
  wt    wall time (microseconds, default)
  cpu   cpu time
  mu    memory usage
  pmu   peak memory usage
  ct    call count

USAGE;

$metric = 'wt';
$args = $_SERVER['argv'] ?? [];
$file = null;

foreach ($args as $i => $a) {
    if ($i === 0) {
        continue;
    }
    if (str_starts_with($a, '--metric=')) {
        $metric = substr($a, 9);
    } elseif (str_starts_with($a, '--')) {
        fwrite(STDERR, $usage);
        exit(1);
    } else {
        $file = $a;
    }
}

if ($file === null || !is_file($file)) {
    fwrite(STDERR, $usage);
    exit(1);
}

$validMetrics = ['wt', 'cpu', 'mu', 'pmu', 'ct'];
if (!in_array($metric, $validMetrics, true)) {
    fwrite(STDERR, "Invalid metric: $metric. Valid: " . implode(', ', $validMetrics) . "\n");
    exit(1);
}

$data = loadXhprofData($file);
if ($data === null) {
    fwrite(STDERR, "Invalid xhprof data\n");
    exit(1);
}

[, $callees, $roots] = buildCallGraph($data);

$stacks = [];
foreach ($roots as $root) {
    walkStack($root, [], 0, $metric, $callees, $stacks);
}

foreach ($stacks as $stack => $weight) {
    echo "$stack $weight\n";
}

function walkStack(
    string $func,
    array $stack,
    float $inclusiveWeight,
    string $metric,
    array $callees,
    array &$stacks,
    array $path = []
): float {
    if (isset($path[$func])) {
        return 0;
    }
    $path[$func] = true;
    $stack[] = $func;

    if (!isset($callees[$func]) || $callees[$func] === []) {
        $weight = $inclusiveWeight > 0 ? $inclusiveWeight : 1;
        $name = implode(';', $stack);
        $stacks[$name] = ($stacks[$name] ?? 0) + $weight;
        return 0;
    }

    $children = $callees[$func];
    $totalChildren = 0;
    foreach ($children as $child) {
        $totalChildren += getMetricValue($child['data'], $metric);
    }

    foreach ($children as $child) {
        $childWeight = getMetricValue($child['data'], $metric);
        walkStack($child['func'], $stack, $childWeight, $metric, $callees, $stacks, $path);
    }

    if ($totalChildren === 0) {
        $name = implode(';', $stack);
        $weight = $inclusiveWeight > 0 ? $inclusiveWeight : 1;
        $stacks[$name] = ($stacks[$name] ?? 0) + $weight;
    }

    return $totalChildren;
}
