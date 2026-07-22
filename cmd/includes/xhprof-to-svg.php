#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/xhprof-lib.php';

$usage = <<<USAGE
Usage: php xhprof-to-svg.php [--metric=wt|cpu|mu|pmu|ct] <file.xhprof>

Generates an interactive SVG flamegraph from an xhprof profile.
Output saved as <file>.xhprof.svg alongside the profile.

USAGE;

$metric = 'wt';
$file = null;

foreach (array_slice($_SERVER['argv'] ?? [], 1) as $a) {
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

$basename = basename($file);
$outPath = $file . '.svg';

$svg = generateFlamegraphSvg($data, $basename, $metric);

file_put_contents($outPath, $svg);
fwrite(STDERR, "Flamegraph saved: $outPath\n");

function generateFlamegraphSvg(array $data, string $title, string $metric): string
{
    $width = 1200;
    $frameHeight = 16;
    $headerHeight = 50;
    $minWidth = 1;

    $stacks = buildStacks($data, $metric);
    if ($stacks === []) {
        return '<svg width="' . $width . '" height="200" xmlns="http://www.w3.org/2000/svg" style="background:#1a1a2e"><text x="10" y="20" fill="white">No profile data</text></svg>';
    }

    $total = 0;
    foreach ($stacks as $s) {
        $total += $s['weight'];
    }
    if ($total <= 0) {
        $total = 1;
    }

    $maxDepth = 0;
    foreach ($stacks as $s) {
        $maxDepth = max($maxDepth, count($s['frames']));
    }

    $svgHeight = $headerHeight + ($maxDepth + 1) * $frameHeight + 30;

    usort($stacks, fn ($a, $b) => strcmp(implode("\0", $a['frames']), implode("\0", $b['frames'])));

    $colorMap = [];
    $colorFor = function (string $name) use (&$colorMap): string {
        if (isset($colorMap[$name])) {
            return $colorMap[$name];
        }
        $h = crc32($name);
        $r = ($h & 0xFF) % 180 + 50;
        $g = (($h >> 4) & 0xFF) % 160 + 60;
        $b = (($h >> 8) & 0xFF) % 200 + 40;
        $c = sprintf('#%02x%02x%02x', $r, $g, $b);
        $colorMap[$name] = $c;
        return $c;
    };

    $offsets = [];
    $rects = [];
    $funcSet = [];

    foreach ($stacks as $s) {
        $w = max($minWidth, $s['weight'] * $width / $total);
        for ($d = 0, $dMax = count($s['frames']); $d < $dMax; $d++) {
            $func = $s['frames'][$d];
            $level = $d;

            if (!isset($offsets[$level])) {
                $offsets[$level] = 0.0;
            }
            $x = $offsets[$level];
            $y = $headerHeight + $level * $frameHeight;

            $funcSet[$func] = true;

            $rects[] = [
                'x' => $x, 'y' => $y,
                'w' => $w, 'h' => $frameHeight - 1,
                'func' => $func, 'weight' => $s['weight'],
                'color' => $colorFor($func),
            ];
        }
        for ($d = 0, $dMax = count($s['frames']); $d < $dMax; $d++) {
            $offsets[$d] = ($offsets[$d] ?? 0.0) + $w;
        }
    }

    $rectsXml = '';
    foreach ($rects as $i => $r) {
        $funcEsc = htmlspecialchars($r['func'], ENT_QUOTES);
        $pct = number_format($r['weight'] / $total * 100, 2);
        $xStr = number_format($r['x'], 2);
        $wStr = number_format($r['w'], 2);

        $rectsXml .= sprintf(
            '<rect id="f%d" x="%s" y="%d" width="%s" height="%d" fill="%s" stroke="rgba(0,0,0,0.3)" stroke-width="0.5" data-func="%s" data-pct="%s"><title>%s — %.2f%% (%s samples)</title></rect>' . "\n",
            $i,
            $xStr,
            (int)$r['y'],
            $wStr,
            (int)$r['h'],
            $r['color'],
            $funcEsc,
            $pct,
            $funcEsc,
            $r['weight'] / $total * 100,
            number_format($r['weight'])
        );
    }

    $funcCount = count($funcSet);

    return <<<SVG
<svg width="{$width}" height="{$svgHeight}" xmlns="http://www.w3.org/2000/svg" style="background:#0d1117;font-family:monospace">
<defs>
  <style>
    .title{fill:#f0f6fc;font-size:14px;font-weight:bold}
    .info{fill:#8b949e;font-size:11px}
    .legend{fill:#8b949e;font-size:10px}
  </style>
</defs>

<rect x="0" y="0" width="{$width}" height="{$svgHeight}" fill="#0d1117"/>

<text x="10" y="20" class="title">XHProf Flamegraph — {$title}</text>
<text x="10" y="36" class="info">Samples: {$total} &middot; Functions: {$funcCount} &middot; Hover for details</text>

<g id="frames">{$rectsXml}</g>

<script type="text/javascript"><![CDATA[
(function() {
  var frames = document.getElementById('frames');
  var prev = null;

  frames.addEventListener('mouseover', function(e) {
    var t = e.target;
    if (t.tagName !== 'rect') return;
    if (prev) prev.setAttribute('stroke', 'rgba(0,0,0,0.3)');
    t.setAttribute('stroke', '#ffcc00');
    t.setAttribute('stroke-width', '2');
    prev = t;
  });

  frames.addEventListener('mouseout', function(e) {
    var t = e.target;
    if (t.tagName !== 'rect') return;
    t.setAttribute('stroke', 'rgba(0,0,0,0.3)');
    t.setAttribute('stroke-width', '0.5');
    if (prev === t) prev = null;
  });

  frames.addEventListener('click', function(e) {
    var t = e.target;
    if (t.tagName !== 'rect') return;
    var func = t.getAttribute('data-func');
    var all = frames.querySelectorAll('rect');
    var found = false;
    all.forEach(function(r) {
      var f = r.getAttribute('data-func');
      if (f === func) {
        r.style.display = 'inline';
        found = true;
      } else {
        r.style.display = 'none';
      }
    });
  });

  document.addEventListener('dblclick', function() {
    var all = frames.querySelectorAll('rect');
    all.forEach(function(r) { r.style.display = 'inline'; });
  });
})();
]]></script>
</svg>
SVG;
}

function buildStacks(array $data, string $metric): array
{
    [, $callees, $roots] = buildCallGraph($data);

    $stacks = [];

    $walk = function (string $func, array $stack, float $weight, array &$visited = []) use (&$walk, &$stacks, $callees, $metric): void {
        if (isset($visited[$func])) {
            return;
        }
        $visited[$func] = true;
        $stack[] = $func;
        if (!isset($callees[$func]) || $callees[$func] === []) {
            $stacks[] = ['frames' => $stack, 'weight' => max(1, (int)ceil($weight))];
            return;
        }
        foreach ($callees[$func] as $c) {
            $w = getMetricValue($c['data'], $metric);
            $walk($c['func'], $stack, $w > 0 ? $w : $weight, $visited);
        }
    };

    foreach ($roots as $r) {
        $walk($r, [], 1);
    }

    if ($stacks === []) {
        $stacks[] = ['frames' => ['main()'], 'weight' => 1];
    }

    return $stacks;
}
