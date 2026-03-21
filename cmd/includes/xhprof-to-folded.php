#!/usr/bin/env php
<?php

declare(strict_types=1);

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

$data = unserialize(file_get_contents($file));
if (!is_array($data)) {
    fwrite(STDERR, "Invalid xhprof data\n");
    exit(1);
}

[$callers, $callees, $roots, $mainKey] = buildGraph($data);

$stacks = [];
foreach ($roots as $root) {
    walkStack($root, [], 0, $metric, $callers, $callees, $stacks);
}

foreach ($stacks as $stack => $weight) {
    echo "$stack $weight\n";
}

function buildGraph(array $data): array
{
    $callers = [];
    $callees = [];
    $hasParent = [];

    foreach ($data as $key => $val) {
        if ($key === 'main()') {
            continue;
        }
        $parts = explode('==>', $key, 2);
        if (count($parts) === 2) {
            [$parent, $child] = $parts;
            $callees[$parent][] = ['func' => $child, 'data' => $val];
            $callers[$child][] = ['func' => $parent, 'data' => $val];
            $hasParent[$child] = true;
        } elseif (count($parts) === 1) {
            $callees['main()'][] = ['func' => $parts[0], 'data' => $val];
            $callers[$parts[0]][] = ['func' => 'main()', 'data' => $val];
            $hasParent[$parts[0]] = true;
        }
    }

    $roots = [];
    foreach ($callees as $func => $children) {
        if (!isset($hasParent[$func])) {
            $roots[] = $func;
        }
    }
    if ($roots === []) {
        $roots = ['main()'];
    }

    if (isset($data['main()'])) {
        $mainKey = 'main()';
    } else {
        $mainKey = $roots[0] ?? 'main()';
    }

    return [$callers, $callees, $roots, $mainKey];
}

function getValue(array $data, string $metric): float
{
    return match ($metric) {
        'wt' => (float)($data['wt'] ?? 0),
        'cpu' => (float)($data['cpu'] ?? 0),
        'mu' => (float)($data['mu'] ?? 0),
        'pmu' => (float)($data['pmu'] ?? 0),
        'ct' => (float)($data['ct'] ?? 1),
        default => (float)($data['wt'] ?? 0),
    };
}

function walkStack(
    string $func,
    array $stack,
    float $inclusiveWeight,
    string $metric,
    array $callers,
    array $callees,
    array &$stacks
): float {
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
        $totalChildren += getValue($child['data'], $metric);
    }

    foreach ($children as $child) {
        $childWeight = getValue($child['data'], $metric);
        walkStack($child['func'], $stack, $childWeight, $metric, $callers, $callees, $stacks);
    }

    if ($totalChildren === 0) {
        $name = implode(';', $stack);
        $weight = $inclusiveWeight > 0 ? $inclusiveWeight : 1;
        $stacks[$name] = ($stacks[$name] ?? 0) + $weight;
    }

    return $totalChildren;
}
