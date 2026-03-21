<?php

declare(strict_types=1);

$storageDir = '/var/www/xhprof-inbox/storage';

$file = $_GET['file'] ?? '';
if (!$file || str_contains($file, '..')) {
    http_response_code(400);
    exit('Invalid file parameter');
}

$path = $storageDir . '/' . $file;
if (!is_file($path) || !str_ends_with($path, '.xhprof')) {
    http_response_code(404);
    exit('File not found');
}

$data = @unserialize(file_get_contents($path));
if (!is_array($data)) {
    http_response_code(400);
    exit('Invalid xhprof data');
}

$basename = basename($file);

// Build call graph from xhprof data
$edges = [];
$nodes = [];
$totals = ['wt' => 0, 'cpu' => 0, 'mu' => 0, 'pmu' => 0];

foreach ($data as $key => $val) {
    if ($key === 'main()') {
        $totals['wt'] += (int)($val['wt'] ?? 0);
        $totals['cpu'] += (int)($val['cpu'] ?? 0);
        $totals['mu'] += (int)($val['mu'] ?? 0);
        $totals['pmu'] += (int)($val['pmu'] ?? 0);
        continue;
    }
    $parts = explode('==>', $key, 2);
    $parent = $parts[0] ?? '';
    $child = $parts[1] ?? $parts[0];

    $wt = (int)($val['wt'] ?? 0);
    $cpu = (int)($val['cpu'] ?? 0);
    $ct = (int)($val['ct'] ?? 0);

    $edgeKey = $parent . '->' . $child;
    if (isset($edges[$edgeKey])) {
        $edges[$edgeKey]['wt'] += $wt;
        $edges[$edgeKey]['cpu'] += $cpu;
        $edges[$edgeKey]['ct'] += $ct;
    } else {
        $edges[$edgeKey] = ['parent' => $parent, 'child' => $child, 'wt' => $wt, 'cpu' => $cpu, 'ct' => $ct];
    }

    if (!isset($nodes[$parent])) {
        $nodes[$parent] = ['wt' => 0, 'cpu' => 0, 'ct' => 0];
    }
    if (!isset($nodes[$child])) {
        $nodes[$child] = ['wt' => 0, 'cpu' => 0, 'ct' => 0];
    }
    $nodes[$parent]['wt'] += $wt;
    $nodes[$parent]['cpu'] += $cpu;
    $nodes[$parent]['ct'] += $ct;
}

$totalWt = $totals['wt'] ?: 1;
$maxWt = 0;
foreach ($edges as $e) {
    $maxWt = max($maxWt, $e['wt']);
}
$maxWt = $maxWt ?: 1;

// Generate DOT
$dot = "digraph XHProf {\n";
$dot .= "  rankdir=LR;\n";
$dot .= "  bgcolor=\"#0d1117\";\n";
$dot .= "  fontcolor=\"#e6edf3\";\n";
$dot .= "  fontname=\"Helvetica\";\n";
$dot .= "  fontsize=12;\n";
$dot .= "  label=\"Call Graph - " . addslashes($basename) . "\";\n";
$dot .= "  labeljust=l;\n";
$dot .= "  pad=0.5;\n";
$dot .= "  nodesep=0.3;\n";
$dot .= "  ranksep=1.2;\n";
$dot .= "  edge [fontname=\"Helvetica\", fontsize=9, fontcolor=\"#8b949e\"];\n";
$dot .= "  node [fontname=\"Helvetica\", fontsize=10, fontcolor=\"#e6edf3\", shape=box, style=\"rounded,filled\", fillcolor=\"#161b22\", color=\"#30363d\", penwidth=1];\n\n";

// Sort edges by weight descending for visual clarity
usort($edges, fn ($a, $b) => $b['wt'] - $a['wt']);

// Take top edges to avoid overwhelming the graph
$maxEdges = 200;
$edges = array_slice($edges, 0, $maxEdges);

$nodeColors = [];
foreach ($edges as $e) {
    $nodeColors[$e['parent']] = true;
    $nodeColors[$e['child']] = true;
}

// Define node colors based on exclusive wall time
$nodeMaxWt = 0;
foreach ($nodeColors as $name => $_) {
    $nodeMaxWt = max($nodeMaxWt, $nodes[$name]['wt'] ?? 0);
}
$nodeMaxWt = $nodeMaxWt ?: 1;

foreach ($nodeColors as $name => $_) {
    $wt = $nodes[$name]['wt'] ?? 0;
    $pct = $wt / $nodeMaxWt;
    // Heat color: blue (cold) -> red (hot)
    $r = min(255, (int)(50 + $pct * 205));
    $g = min(255, (int)(150 - $pct * 120));
    $b = min(255, (int)(200 - $pct * 150));
    $fill = sprintf('#%02x%02x%02x', $r, max(0, $g), max(0, $b));
    $label = htmlspecialchars($name, ENT_QUOTES);
    $escaped = addslashes($name);
    $tooltip = "$escaped\\n" . number_format($wt / 1000, 2) . " ms (" . number_format($wt / $totalWt * 100, 1) . "%)";
    $dot .= "  \"$escaped\" [fillcolor=\"$fill\", tooltip=\"$tooltip\"];\n";
}

$dot .= "\n";

$edgeCount = count($edges);
foreach ($edges as $i => $e) {
    $pct = $e['wt'] / $totalWt * 100;
    $width = max(0.5, $e['wt'] / $maxWt * 8);

    $parent = addslashes($e['parent']);
    $child = addslashes($e['child']);
    $label = number_format($e['wt'] / 1000, 1) . "ms\\n(" . number_format($pct, 1) . "%)";

    $alpha = min(1.0, max(0.2, $pct / 10));
    // Color edge by heat
    $er = 88;
    $eg = 166;
    $eb = 255;
    // Fade for less important edges
    $edgeColor = sprintf('#%02x%02x%02x', (int)($er * $alpha + 48 * (1 - $alpha)), (int)($eg * $alpha + 48 * (1 - $alpha)), (int)($eb * $alpha + 48 * (1 - $alpha)));

    $dot .= "  \"$parent\" -> \"$child\" [label=\"$label\", penwidth=$width, color=\"$edgeColor\", fontcolor=\"#8b949e\"];\n";
}

$dot .= "}\n";

// Run graphviz
$descriptorspec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$proc = proc_open('dot -Tsvg', $descriptorspec, $pipes);
if (!is_resource($proc)) {
    http_response_code(500);
    exit('Graphviz (dot) not available. Install graphviz package.');
}

fwrite($pipes[0], $dot);
fclose($pipes[0]);

$svg = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$ret = proc_close($proc);

if ($ret !== 0 || !$svg) {
    http_response_code(500);
    exit('Graphviz rendering failed: ' . htmlspecialchars($stderr));
}

// Ensure SVG is embeddable - strip XML declaration and DOCTYPE
$svg = preg_replace('/<\?xml.*?\?>\n?/', '', $svg);
$svg = preg_replace('/<!DOCTYPE svg[^>]*>\n?/', '', $svg);

$svgUrl = 'storage/' . htmlspecialchars($file) . '.svg';
$hasSvg = file_exists($path . '.svg');

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Graphviz — <?= htmlspecialchars($basename) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,Ubuntu,sans-serif;background:#0d1117;color:#e6edf3;padding:0}
.topbar{background:#161b22;border-bottom:1px solid #30363d;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.topbar h1{font-size:18px;color:#f0f6fc;word-break:break-all}
.topbar-links{display:flex;gap:8px}
.btn{display:inline-flex;align-items:center;gap:5px;padding:5px 14px;border-radius:6px;font-size:12px;font-weight:500;text-decoration:none;border:1px solid transparent;cursor:pointer;transition:all .15s}
.btn-back{background:#21262d;color:#c9d1d9;border-color:#30363d}
.btn-back:hover{background:#30363d}
.btn-xhgui{background:#238636;color:#fff;border-color:#2ea043}
.btn-xhgui:hover{background:#2ea043}
.btn-svg{background:#6e40c9;color:#fff;border-color:#8256d0}
.btn-svg:hover{background:#8256d0}
.btn-dot{background:#1f6feb;color:#fff;border-color:#388bfd;margin-left:4px}
.btn-dot:hover{background:#388bfd}
.info{font-size:13px;color:#8b949e;padding:12px 24px;border-bottom:1px solid #21262d}
.info strong{color:#e6edf3}
.container{padding:20px 24px}
.click-hint{margin-bottom:16px;font-size:12px;color:#8b949e;text-align:center}
.graph-container{background:#0d1117;border:1px solid #30363d;border-radius:8px;padding:20px;overflow:auto}
.graph-container svg{max-width:100%;height:auto;display:block}
.tools{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap}
.tools button{background:#21262d;color:#c9d1d9;border:1px solid #30363d;border-radius:6px;padding:6px 14px;font-size:12px;cursor:pointer;transition:all .15s}
.tools button:hover{background:#30363d}
.tools button.active{background:#1f6feb;border-color:#388bfd;color:#fff}
.stats-row{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px;font-size:12px;color:#8b949e}
.stats-row span{background:#161b22;padding:4px 12px;border-radius:4px;border:1px solid #30363d}
.stats-row strong{color:#e6edf3}
</style>
</head>
<body>

<div class="topbar">
  <h1>Graphviz: <?= htmlspecialchars($basename) ?></h1>
  <div class="topbar-links">
    <a href="index.php" class="btn btn-back">← Back to Inbox</a>
    <a href="xhgui.php?file=<?= urlencode($file) ?>" class="btn btn-xhgui">📊 XHGui</a>
    <?php if ($hasSvg): ?><a href="<?= $svgUrl ?>" class="btn btn-svg" target="_blank">🔥 SVG Flamegraph</a><?php endif; ?>
  </div>
</div>

<div class="info">
  <strong><?= number_format(count($edges)) ?></strong> call edges shown (top <?= $maxEdges ?>), max wall time: <strong><?= number_format($maxWt / 1000, 2) ?> ms</strong>, total: <strong><?= number_format($totalWt / 1000, 2) ?> ms</strong>
</div>

<div class="container">
  <div class="tools">
    <button onclick="zoomTo(0.5)">🔍 Zoom Out</button>
    <button onclick="zoomTo(1)" class="active" id="zoom-1">🔍 100%</button>
    <button onclick="zoomTo(1.5)">🔍 150%</button>
    <button onclick="zoomTo(2)">🔍 200%</button>
    <button onclick="fitToScreen()">⊡ Fit to Screen</button>
    <button onclick="downloadSvg()">⬇ Download SVG</button>
    <button onclick="downloadDot()">⬇ Download DOT</button>
  </div>

  <div class="click-hint">💡 Scroll to zoom • Click & drag to pan • Nodes colored by exclusive wall time</div>

  <div class="graph-container" id="graph-container">
    <?= $svg ?>
  </div>
</div>

<script>
function zoomTo(factor) {
  var svg = document.querySelector('.graph-container svg');
  if (!svg) return;
  var w = svg.getAttribute('width') || svg.viewBox.baseVal.width || 1200;
  svg.style.width = (parseFloat(w) * factor) + 'px';
  document.querySelectorAll('.tools button').forEach(function(b) { b.classList.remove('active'); });
  var btn = document.getElementById('zoom-' + (factor * 10));
  if (btn) btn.classList.add('active');
}

function fitToScreen() {
  var container = document.getElementById('graph-container');
  var svg = container.querySelector('svg');
  if (!svg) return;
  svg.style.width = '100%';
  document.querySelectorAll('.tools button').forEach(function(b) { b.classList.remove('active'); });
}

function downloadSvg() {
  var svg = document.querySelector('.graph-container svg');
  if (!svg) return;
  var html = '<' + '?xml version="1.0" encoding="UTF-8" standalone="no"?>\n<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">\n' + svg.outerHTML;
  var blob = new Blob([html], {type: 'image/svg+xml'});
  var a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = '<?= htmlspecialchars(str_replace('.xhprof', '', $basename)) ?>.callgraph.svg';
  a.click();
  URL.revokeObjectURL(a.href);
}

function downloadDot() {
  var dot = <?= json_encode($dot) ?>;
  var blob = new Blob([dot], {type: 'text/plain'});
  var a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = '<?= htmlspecialchars(str_replace('.xhprof', '', $basename)) ?>.callgraph.dot';
  a.click();
  URL.revokeObjectURL(a.href);
}

// Pan support
(function() {
  var container = document.getElementById('graph-container');
  var isDown = false, startX, startY, scrollLeft, scrollTop;
  container.addEventListener('mousedown', function(e) {
    isDown = true;
    container.style.cursor = 'grabbing';
    startX = e.pageX - container.offsetLeft;
    startY = e.pageY - container.offsetTop;
    scrollLeft = container.scrollLeft;
    scrollTop = container.scrollTop;
  });
  container.addEventListener('mouseleave', function() {
    isDown = false;
    container.style.cursor = 'grab';
  });
  container.addEventListener('mouseup', function() {
    isDown = false;
    container.style.cursor = 'grab';
  });
  container.addEventListener('mousemove', function(e) {
    if (!isDown) return;
    e.preventDefault();
    var x = e.pageX - container.offsetLeft;
    var y = e.pageY - container.offsetTop;
    var walkX = (x - startX) * 1.5;
    var walkY = (y - startY) * 1.5;
    container.scrollLeft = scrollLeft - walkX;
    container.scrollTop = scrollTop - walkY;
  });
  container.style.cursor = 'grab';
})();
</script>
</body>
</html>
