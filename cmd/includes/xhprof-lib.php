<?php

declare(strict_types=1);

/**
 * Shared helpers for CLI xhprof tools (report-lib, to-svg, to-folded, top, prepend).
 */

function loadXhprofData(string $path): ?array
{
    $raw = @file_get_contents($path, false, null);
    if ($raw === false) {
        return null;
    }

    $data = @unserialize($raw, ['allowed_classes' => false]);
    if (!is_array($data)) {
        return null;
    }

    return $data;
}

/**
 * Build caller/callee maps and root functions from raw xhprof rows.
 * Returns [$callers, $callees, $roots].
 */
function buildCallGraph(array $data): array
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

    return [$callers, $callees, $roots];
}

function getMetricValue(array $data, string $metric): float
{
    return match ($metric) {
        'wt' => (float) ($data['wt'] ?? 0),
        'cpu' => (float) ($data['cpu'] ?? 0),
        'mu' => (float) ($data['mu'] ?? 0),
        'pmu' => (float) ($data['pmu'] ?? 0),
        'ct' => (float) ($data['ct'] ?? 1),
        default => (float) ($data['wt'] ?? 0),
    };
}

function buildTreeHtml(array $data, array $rows, array $totals): string
{
    $tree = [];
    foreach ($data as $key => $val) {
        if ($key === 'main()') {
            continue;
        }
        $parts = explode('==>', $key, 2);
        $parent = $parts[0] ?? '';
        $child = $parts[1] ?? $parts[0];
        $tree[$parent][] = ['child' => $child, 'wt' => (int) ($val['wt'] ?? 0), 'ct' => (int) ($val['ct'] ?? 0)];
    }

    $hasParent = [];
    foreach ($tree as $p => $children) {
        foreach ($children as $c) {
            $hasParent[$c['child']] = true;
        }
    }

    $roots = [];
    foreach ($tree as $p => $children) {
        if (!isset($hasParent[$p])) {
            $roots[] = $p;
        }
    }
    if ($roots === []) {
        $roots = array_keys($tree);
    }

    $totalWt = $totals['wt'] ?? 1;

    $render = function (string $func, int $depth, array &$visited = []) use (&$render, &$tree, $totalWt, &$rows): string {
        if (isset($visited[$func])) {
            return '';
        }
        $visited[$func] = true;

        $wt = $rows[$func]['wt'] ?? 0;
        $pct = $totalWt > 0 ? ($wt / $totalWt * 100) : 0;
        $indent = str_repeat('  ', $depth);
        $h = $indent . '<details><summary><span class="func-name">' . htmlspecialchars($func) . '</span> <span class="func-val">' . number_format((float) $pct, 1) . '% / ' . number_format($wt / 1000, 2) . 'ms</span></summary>';
        if (isset($tree[$func])) {
            usort($tree[$func], fn ($a, $b) => $b['wt'] - $a['wt']);
            foreach ($tree[$func] as $c) {
                $h .= $render($c['child'], $depth + 1, $visited);
            }
        }
        $h .= $indent . '</details>';

        return $h;
    };

    $html = '';
    usort($roots, fn ($a, $b) => ($rows[$b]['wt'] ?? 0) - ($rows[$a]['wt'] ?? 0));
    foreach ($roots as $r) {
        $visited = [];
        $html .= $render($r, 0, $visited);
    }

    return $html;
}
