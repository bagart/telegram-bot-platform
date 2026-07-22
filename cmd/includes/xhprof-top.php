<?php

declare(strict_types=1);

require __DIR__ . '/xhprof-lib.php';

/**
 * Quick xhprof dumper — prints wall/cpu/mu top-N for a single serialized profile.
 * Usage: php cmd/includes/xhprof-top.php <profile.xhprof> [N]
 */

$file = $argv[1] ?? '';
$topN = (int) ($argv[2] ?? 25);

if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "usage: php xhprof-top.php <profile.xhprof> [N]\n");
    exit(1);
}

$d = loadXhprofData($file);
if ($d === null) {
    fwrite(STDERR, "cannot unserialize {$file}\n");
    exit(1);
}

$main = $d['main()'] ?? ['wt' => 0, 'mu' => 0, 'pmu' => 0, 'ct' => 0];
echo "=== ".basename(dirname($file))." ===\n";
echo "wt=".round($main['wt'] / 1000)."ms mu=".round($main['mu'] / 1024 / 1024, 2)."MB pmu=".round($main['pmu'] / 1024 / 1024, 2)."MB\n";

$rows = [];
foreach ($d as $k => $v) {
    if ($k === 'main()' || !isset($v['wt'])) {
        continue;
    }
    $rows[$k] = $v;
}
uasort($rows, static fn ($a, $b) => $b['wt'] <=> $a['wt']);

echo "\n--- top {$topN} by WALL TIME (inclusive) ---\n";
printf("  %-66s %10s %9s %6s %12s\n", 'function', 'wt', 'calls', '%wt', 'wt/call');
$i = 0;
foreach ($rows as $k => $v) {
    if ($i++ >= $topN) {
        break;
    }
    $ct = $v['ct'] ?? 0;
    $pct = $main['wt'] > 0 ? ($v['wt'] / $main['wt']) * 100 : 0;
    $per = $ct > 0 ? $v['wt'] / $ct : 0;
    printf("  %-66s %8sms %9d %5.1f%% %10sms\n", substr($k, -66), round($v['wt'] / 1000, 1), $ct, $pct, round($per / 1000, 3));
}
