<?php

declare(strict_types=1);

require __DIR__ . '/lib.php';

$storageDir = '/var/www/xhprof-inbox/storage';

$request = resolveXhprofRequest($storageDir, $_GET['file'] ?? '');
if ($request['status'] === 'bad_param') {
    http_response_code(400);
    exit('Invalid file parameter');
}
if ($request['status'] === 'not_found') {
    http_response_code(404);
    exit('File not found');
}

$file = $request['file'];
$path = $request['path'];
$basename = $request['basename'];

$data = loadXhprofData($path);
if ($data === null) {
    http_response_code(400);
    exit('Invalid xhprof data');
}

$hasHtml = file_exists($path . '.html');
$htmlUrl = 'storage/' . htmlspecialchars($file) . '.html';
$hasSvg = file_exists($path . '.svg');
$svgUrl = 'storage/' . htmlspecialchars($file) . '.svg';

$metricLabels = ['wt' => 'Wall Time', 'cpu' => 'CPU Time', 'mu' => 'Memory Usage', 'pmu' => 'Peak Memory', 'ct' => 'Calls'];

$rows = [];
$totals = ['ct' => 0, 'wt' => 0, 'cpu' => 0, 'mu' => 0, 'pmu' => 0];

foreach ($data as $key => $val) {
    if ($key === 'main()') {
        continue;
    }
    $parts = explode('==>', $key, 2);
    $parent = $parts[0] ?? '';
    $child = $parts[1] ?? $parts[0];

    if (!isset($rows[$child])) {
        $rows[$child] = ['ct' => 0, 'wt' => 0, 'cpu' => 0, 'mu' => 0, 'pmu' => 0, 'parents' => [], 'children' => []];
    }
    $rows[$child]['ct'] += (int)($val['ct'] ?? 0);
    $rows[$child]['wt'] += (int)($val['wt'] ?? 0);
    $rows[$child]['cpu'] += (int)($val['cpu'] ?? 0);
    $rows[$child]['mu'] += (int)($val['mu'] ?? 0);
    $rows[$child]['pmu'] += (int)($val['pmu'] ?? 0);
    $rows[$child]['parents'][] = ['func' => $parent, 'data' => $val];

    $totals['ct'] += (int)($val['ct'] ?? 0);
    $totals['wt'] += (int)($val['wt'] ?? 0);
    $totals['cpu'] += (int)($val['cpu'] ?? 0);
    $totals['mu'] += (int)($val['mu'] ?? 0);
    $totals['pmu'] += (int)($val['pmu'] ?? 0);

    if (!isset($rows[$parent])) {
        $rows[$parent] = ['ct' => 0, 'wt' => 0, 'cpu' => 0, 'mu' => 0, 'pmu' => 0, 'parents' => [], 'children' => []];
    }
    $rows[$parent]['children'][] = ['func' => $child, 'data' => $val];
}

$funcNames = array_keys($rows);
sort($funcNames);

$dataJson = json_encode([
    'rows' => $rows,
    'totals' => $totals,
    'funcNames' => $funcNames,
], JSON_THROW_ON_ERROR);

$treeHtml = buildTreeHtml($data, $rows, $totals);

$measure = $data['main()'] ?? ['wt' => 0, 'cpu' => 0, 'mu' => 0, 'pmu' => 0, 'ct' => count($data) - 1];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>XHGui — <?= htmlspecialchars($basename) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,Ubuntu,sans-serif;background:#0d1117;color:#e6edf3;padding:0}
.topbar{background:#161b22;border-bottom:1px solid #30363d;padding:16px 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.topbar h1{font-size:18px;color:#f0f6fc;word-break:break-all}
.topbar-links{display:flex;gap:8px}
.btn{display:inline-flex;align-items:center;gap:5px;padding:5px 14px;border-radius:6px;font-size:12px;font-weight:500;text-decoration:none;border:1px solid transparent;cursor:pointer;transition:all .15s}
.btn-back{background:#21262d;color:#c9d1d9;border-color:#30363d}
.btn-back:hover{background:#30363d}
.btn-html{background:#1f6feb;color:#fff;border-color:#388bfd}
.btn-html:hover{background:#388bfd}
.btn-svg{background:#6e40c9;color:#fff;border-color:#8256d0}
.btn-svg:hover{background:#8256d0}
.btn-graphviz{background:#9e6a03;color:#fff;border-color:#bb8009}
.btn-graphviz:hover{background:#bb8009}
.container{max-width:1400px;margin:0 auto;padding:20px 24px}
.summary-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px}
.summary-card{background:#161b22;border:1px solid #30363d;border-radius:8px;padding:14px 16px}
.summary-card .label{font-size:11px;color:#8b949e;text-transform:uppercase;letter-spacing:.5px}
.summary-card .value{font-size:20px;font-weight:600;color:#f0f6fc;margin-top:4px;font-family:"SF Mono","JetBrains Mono","Consolas",monospace}
.summary-card .value.wt{color:#58a6ff}
.summary-card .value.cpu{color:#d2a8ff}
.summary-card .value.mem{color:#7ee787}
.summary-card .value.ct{color:#e3b341}
.tabs{display:flex;gap:4px;margin-bottom:16px;border-bottom:1px solid #30363d;flex-wrap:wrap}
.tab{padding:8px 18px;cursor:pointer;border:1px solid #30363d;border-bottom:none;border-radius:6px 6px 0 0;background:#161b22;color:#8b949e;font-size:13px;user-select:none}
.tab.active{background:#0d1117;color:#f0f6fc;border-color:#30363d;border-bottom-color:#0d1117;margin-bottom:-1px}
.tab-content{display:none}
.tab-content.active{display:block}
table{width:100%;border-collapse:collapse;font-size:12px;font-family:"SF Mono","JetBrains Mono","Consolas",monospace}
th,td{padding:6px 10px;text-align:right;border-bottom:1px solid #21262d;white-space:nowrap}
th{background:#161b22;color:#8b949e;font-weight:600;cursor:pointer;position:sticky;top:0;z-index:1;user-select:none}
td:first-child,th:first-child{text-align:left}
td.name{max-width:500px;overflow:hidden;text-overflow:ellipsis;direction:rtl;text-align:right}
.bar{display:inline-block;height:12px;background:#1f6feb;border-radius:2px;vertical-align:middle;margin-right:6px;min-width:2px}
.bar.cpu{background:#8256d0}
.bar.mem{background:#3fb950}
tr:hover td{background:#161b22}
a{color:#58a6ff;text-decoration:none;cursor:pointer}
a:hover{text-decoration:underline}
.search-box{margin-bottom:12px}
.search-box input{width:100%;padding:8px 12px;background:#0d1117;border:1px solid #30363d;border-radius:6px;color:#e6edf3;font-size:13px;font-family:"SF Mono",monospace}
.search-box input:focus{border-color:#58a6ff;outline:none}
.controls{display:flex;gap:12px;align-items:center;margin-bottom:12px;flex-wrap:wrap}
.controls label{font-size:12px;color:#8b949e;display:flex;align-items:center;gap:6px}
.controls select{padding:4px 8px;background:#161b22;border:1px solid #30363d;border-radius:4px;color:#e6edf3;font-size:12px}
.legend{margin-bottom:12px;font-size:12px;color:#8b949e}
.legend span{margin-right:16px}
.subtitle{font-size:12px;color:#8b949e;margin-bottom:8px}
.tree{font-family:"SF Mono","JetBrains Mono","Consolas",monospace;font-size:12px;line-height:1.6}
.tree summary{cursor:pointer;padding:2px 4px;border-radius:3px}
.tree summary:hover{background:#161b22}
.tree .func-name{color:#58a6ff}
.tree .func-val{color:#8b949e;margin-left:8px}
.object-container svg{width:100%;max-width:1200px;height:auto;border:1px solid #30363d;border-radius:6px;background:#0d1117}
@media(max-width:768px){td,th{font-size:11px;padding:4px 6px}td.name{max-width:200px}.summary-cards{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>

<div class="topbar">
  <h1>XHGui: <?= htmlspecialchars($basename) ?></h1>
  <div class="topbar-links">
    <a href="index.php" class="btn btn-back">← Back to Inbox</a>
    <a href="graphviz.php?file=<?= urlencode($file) ?>" class="btn btn-graphviz">🔀 Graphviz</a>
    <?php if ($hasHtml): ?><a href="<?= $htmlUrl ?>" class="btn btn-html" target="_blank">📄 HTML</a><?php endif; ?>
    <?php if ($hasSvg): ?><a href="<?= $svgUrl ?>" class="btn btn-svg" target="_blank">🔥 SVG</a><?php endif; ?>
  </div>
</div>

<div class="container">
  <div class="summary-cards">
    <div class="summary-card">
      <div class="label">Wall Time</div>
      <div class="value wt"><?= formatMicroseconds((int)($measure['wt'] ?? 0)) ?></div>
    </div>
    <div class="summary-card">
      <div class="label">CPU Time</div>
      <div class="value cpu"><?= formatMicroseconds((int)($measure['cpu'] ?? 0)) ?></div>
    </div>
    <div class="summary-card">
      <div class="label">Memory Usage</div>
      <div class="value mem"><?= formatMemory((int)($measure['mu'] ?? 0)) ?></div>
    </div>
    <div class="summary-card">
      <div class="label">Peak Memory</div>
      <div class="value mem"><?= formatMemory((int)($measure['pmu'] ?? 0)) ?></div>
    </div>
    <div class="summary-card">
      <div class="label">Functions Called</div>
      <div class="value ct"><?= number_format($totals['ct']) ?></div>
    </div>
    <div class="summary-card">
      <div class="label">Unique Functions</div>
      <div class="value ct"><?= number_format(count($rows)) ?></div>
    </div>
  </div>

  <div class="tabs">
    <div class="tab active" data-tab="flat">Flat View</div>
    <div class="tab" data-tab="func">Function View</div>
    <div class="tab" data-tab="tree">Call Graph</div>
    <div class="tab" data-tab="flame">Flamegraph</div>
  </div>

  <div id="tab-flat" class="tab-content active">
    <div class="controls">
      <label>Metric: <select id="flat-metric">
<?php foreach ($metricLabels as $k => $v): $sel = $k === 'wt' ? 'selected' : ''; ?>
        <option value="<?= $k ?>" <?= $sel ?>><?= $v ?></option>
<?php endforeach; ?>
      </select></label>
      <label>Sort: <select id="flat-sort">
        <option value="desc">High → Low</option>
        <option value="asc">Low → High</option>
      </select></label>
      <label>Show: <select id="flat-limit">
        <option value="50">50</option>
        <option value="100" selected>100</option>
        <option value="200">200</option>
        <option value="500">500</option>
        <option value="0">All</option>
      </select></label>
    </div>
    <div class="search-box"><input type="text" id="flat-search" placeholder="Filter functions..."></div>
    <table id="flat-table">
      <thead><tr>
        <th data-key="fn">Function</th>
        <th data-key="ct" class="sorted">Calls <span class="sort">▲</span></th>
        <th data-key="wt" class="sorted">Wall Time <span class="sort">▲</span></th>
        <th data-key="cpu">CPU Time <span class="sort">▲</span></th>
        <th data-key="mu">Memory <span class="sort">▲</span></th>
        <th data-key="pmu">Peak Memory <span class="sort">▲</span></th>
      </tr></thead>
      <tbody id="flat-body"></tbody>
    </table>
  </div>

  <div id="tab-func" class="tab-content">
    <div class="search-box"><input type="text" id="func-search" placeholder="Search function..."></div>
    <select id="func-select" style="width:100%;padding:8px;background:#161b22;border:1px solid #30363d;border-radius:6px;color:#e6edf3;font-size:13px;margin-bottom:12px">
      <option value="">— select function —</option>
<?php foreach ($funcNames as $fn): ?>
      <option value="<?= htmlspecialchars($fn) ?>"><?= htmlspecialchars($fn) ?></option>
<?php endforeach; ?>
    </select>
    <div id="func-detail"></div>
  </div>

  <div id="tab-tree" class="tab-content">
    <div class="subtitle">Top-down call graph (wall time, inclusive)</div>
    <div class="tree"><?= $treeHtml ?></div>
  </div>

  <div id="tab-flame" class="tab-content">
    <div class="subtitle">Flamegraph — click to zoom, double-click to reset</div>
    <div class="object-container">
      <?php if ($hasSvg): ?>
      <object data="<?= $svgUrl ?>" type="image/svg+xml" width="100%" height="600">
        <img src="<?= $svgUrl ?>" alt="Flamegraph" style="width:100%">
      </object>
      <?php else: ?>
      <p style="color:#8b949e;padding:40px;text-align:center">No SVG flamegraph available. Generate one with: <code>php cmd/xhprof report <?= htmlspecialchars($file) ?></code></p>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
var DATA = <?= $dataJson ?>;

function fmt(n) {
  if (typeof n !== "number") return '\u2014';
  if (Math.abs(n) >= 1e9) return (n / 1e9).toFixed(2) + "s";
  if (Math.abs(n) >= 1e6) return (n / 1e6).toFixed(2) + "s";
  if (Math.abs(n) >= 1000) return (n / 1000).toFixed(2) + "ms";
  return n.toFixed(0) + "\u00b5s";
}
function fmtMem(n) {
  if (typeof n !== "number") return '\u2014';
  if (Math.abs(n) >= (1 << 30)) return (n / (1 << 30)).toFixed(2) + "GiB";
  if (Math.abs(n) >= (1 << 20)) return (n / (1 << 20)).toFixed(2) + "MiB";
  if (Math.abs(n) >= 1024) return (n / 1024).toFixed(2) + "KiB";
  return n + "B";
}
function fmtCt(n) {
  if (typeof n !== "number") return '\u2014';
  return n.toLocaleString();
}
function escHtml(s) {
  var d = document.createElement("div");
  d.textContent = s;
  return d.innerHTML;
}

var rows = DATA.rows;
var totals = DATA.totals;

function getVal(r, m) {
  if (m === "fn") return r;
  if (m === "ct") return r.ct;
  return r[m] || 0;
}

function renderFlat() {
  var metric = document.getElementById("flat-metric").value;
  var sortDir = document.getElementById("flat-sort").value;
  var limit = parseInt(document.getElementById("flat-limit").value);
  var q = document.getElementById("flat-search").value.toLowerCase();

  var list = Object.keys(rows).filter(function(n) {
    return !q || n.toLowerCase().indexOf(q) !== -1;
  });

  var total = totals[metric] || 1;
  list.sort(function(a, b) {
    var va = getVal(rows[a], metric) || 0;
    var vb = getVal(rows[b], metric) || 0;
    return sortDir === "desc" ? vb - va : va - vb;
  });

  if (limit > 0) list = list.slice(0, limit);

  var tbody = document.getElementById("flat-body");
  var h = "";
  for (var i = 0; i < list.length; i++) {
    var name = list[i];
    var r = rows[name];
    var v = getVal(r, metric) || 0;
    var barW = Math.max(2, v / total * 500);
    h += '<tr><td class="name"><a data-func="' + escHtml(name).replace(/"/g, '&quot;') + '">' + escHtml(name) + '</a></td>';
    h += '<td>' + fmtCt(r.ct) + '</td>';
    h += '<td><span class="bar" style="width:' + barW + 'px"></span>' + fmt(r.wt) + '</td>';
    h += '<td>' + fmt(r.cpu) + '</td>';
    h += '<td>' + fmtMem(r.mu) + '</td>';
    h += '<td>' + fmtMem(r.pmu) + '</td></tr>';
  }
  tbody.innerHTML = h;
}

function renderFunc(name) {
  var div = document.getElementById("func-detail");
  if (!name || !rows[name]) {
    div.innerHTML = '<p style="color:#8b949e">Select a function</p>';
    return;
  }
  var r = rows[name];
  var h = '<h3 style="margin-bottom:8px;font-size:15px">' + escHtml(name) + '</h3>';
  h += '<div class="legend"><span>Calls: ' + fmtCt(r.ct) + '</span>';
  h += '<span>Wall: ' + fmt(r.wt) + '</span>';
  h += '<span>CPU: ' + fmt(r.cpu) + '</span>';
  h += '<span>Mem: ' + fmtMem(r.mu) + '</span>';
  h += '<span>Peak: ' + fmtMem(r.pmu) + '</span></div>';

  if (r.parents && r.parents.length) {
    h += '<h4 style="margin:8px 0 4px;font-size:13px;color:#8b949e">Called by (' + r.parents.length + '):</h4>';
    h += '<table><tr><th>Function</th><th>Calls</th><th>Wall</th><th>CPU</th><th>Mem</th></tr>';
    for (var i = 0; i < r.parents.length; i++) {
      var p = r.parents[i];
      var d = p.data;
      h += '<tr><td><a data-func="' + escHtml(p.func).replace(/"/g, '&quot;') + '">' + escHtml(p.func) + '</a></td>';
      h += '<td>' + fmtCt(d.ct) + '</td><td>' + fmt(d.wt) + '</td><td>' + fmt(d.cpu) + '</td><td>' + fmtMem(d.mu) + '</td></tr>';
    }
    h += '</table>';
  }
  if (r.children && r.children.length) {
    h += '<h4 style="margin:12px 0 4px;font-size:13px;color:#8b949e">Calls (' + r.children.length + '):</h4>';
    h += '<table><tr><th>Function</th><th>Calls</th><th>Wall</th><th>CPU</th><th>Mem</th></tr>';
    for (var i = 0; i < r.children.length; i++) {
      var c = r.children[i];
      var d = c.data;
      h += '<tr><td><a data-func="' + escHtml(c.func).replace(/"/g, '&quot;') + '">' + escHtml(c.func) + '</a></td>';
      h += '<td>' + fmtCt(d.ct) + '</td><td>' + fmt(d.wt) + '</td><td>' + fmt(d.cpu) + '</td><td>' + fmtMem(d.mu) + '</td></tr>';
    }
    h += '</table>';
  }
  div.innerHTML = h;
}

document.getElementById("flat-metric").addEventListener("change", renderFlat);
document.getElementById("flat-sort").addEventListener("change", renderFlat);
document.getElementById("flat-limit").addEventListener("change", renderFlat);
document.getElementById("flat-search").addEventListener("input", renderFlat);

document.getElementById("func-search").addEventListener("input", function() {
  var q = this.value.toLowerCase();
  var sel = document.getElementById("func-select");
  for (var i = 0; i < sel.options.length; i++) {
    var o = sel.options[i];
    o.style.display = (!o.value || o.value.toLowerCase().indexOf(q) !== -1) ? "" : "none";
  }
});

document.getElementById("func-select").addEventListener("change", function() {
  renderFunc(this.value);
});

document.getElementById("flat-body").addEventListener("click", function(e) {
  var t = e.target;
  while (t && t.tagName !== "A") t = t.parentNode;
  if (t && t.hasAttribute("data-func")) {
    var fn = t.getAttribute("data-func");
    document.getElementById("func-select").value = fn;
    document.querySelectorAll(".tab").forEach(function(el) { el.classList.remove("active"); });
    document.querySelectorAll(".tab-content").forEach(function(el) { el.classList.remove("active"); });
    document.querySelector('[data-tab="func"]').classList.add("active");
    document.getElementById("tab-func").classList.add("active");
    renderFunc(fn);
  }
});

document.getElementById("func-detail").addEventListener("click", function(e) {
  var t = e.target;
  while (t && t.tagName !== "A") t = t.parentNode;
  if (t && t.hasAttribute("data-func")) {
    var fn = t.getAttribute("data-func");
    document.getElementById("func-select").value = fn;
    renderFunc(fn);
  }
});

document.querySelectorAll(".tab").forEach(function(tab) {
  tab.addEventListener("click", function() {
    document.querySelectorAll(".tab").forEach(function(el) { el.classList.remove("active"); });
    document.querySelectorAll(".tab-content").forEach(function(el) { el.classList.remove("active"); });
    this.classList.add("active");
    document.getElementById("tab-" + this.dataset.tab).classList.add("active");
  });
});

renderFlat();
</script>
</body>
</html>
