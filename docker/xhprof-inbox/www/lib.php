<?php

declare(strict_types=1);

/**
 * Shared helpers for the xhprof-inbox web viewer (index, xhgui, graphviz).
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
 * Validate a `?file=` request against the inbox storage dir.
 * Returns the resolved path details on success, or a status describing the failure.
 */
function resolveXhprofRequest(string $storageDir, string $requested): array
{
    if ($requested === '' || str_contains($requested, '..')) {
        return ['status' => 'bad_param', 'file' => $requested, 'path' => null, 'basename' => null];
    }

    $path = $storageDir . '/' . $requested;
    if (!is_file($path) || !str_ends_with($path, '.xhprof')) {
        return ['status' => 'not_found', 'file' => $requested, 'path' => $path, 'basename' => basename($requested)];
    }

    return ['status' => 'ok', 'file' => $requested, 'path' => $path, 'basename' => basename($requested)];
}

function formatMicroseconds(int $value): string
{
    if ($value >= 1_000_000) {
        return number_format($value / 1_000_000, 3) . ' s';
    }
    if ($value >= 1_000) {
        return number_format($value / 1_000, 2) . ' ms';
    }
    return $value . ' µs';
}

function formatMemory(int $value): string
{
    if ($value >= (1 << 30)) {
        return number_format($value / (1 << 30), 2) . ' GiB';
    }
    if ($value >= (1 << 20)) {
        return number_format($value / (1 << 20), 2) . ' MiB';
    }
    if ($value >= 1024) {
        return number_format($value / 1024, 2) . ' KiB';
    }
    return $value . ' B';
}

/**
 * Read the `main()` summary of a profile, using a sidecar cache to avoid
 * unserializing every profile on each inbox page load.
 */
function loadProfileSummary(string $path): ?array
{
    $cache = $path . '.summary.json';
    $mtime = (int) @filemtime($path);
    if ($mtime > 0) {
        $raw = @file_get_contents($cache, false, null);
        $parsed = $raw !== false ? json_decode($raw, true) : null;
        if (is_array($parsed) && ($parsed['mtime'] ?? 0) === $mtime && is_array($parsed['data'] ?? null)) {
            return $parsed['data'];
        }
    }

    $data = loadXhprofData($path);
    if ($data === null) {
        return null;
    }

    $main = $data['main()'] ?? ['wt' => 0, 'cpu' => 0, 'mu' => 0, 'pmu' => 0];
    $summary = [
        'wt' => (int) ($main['wt'] ?? 0),
        'cpu' => (int) ($main['cpu'] ?? 0),
        'mu' => (int) ($main['mu'] ?? 0),
        'pmu' => (int) ($main['pmu'] ?? 0),
        'ct' => count($data) - 1,
    ];

    if ($mtime > 0) {
        $payload = json_encode(['mtime' => $mtime, 'data' => $summary]);
        if (is_string($payload)) {
            @file_put_contents($cache, $payload);
        }
    }

    return $summary;
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
