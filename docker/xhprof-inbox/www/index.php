<?php

declare(strict_types=1);

require __DIR__ . '/lib.php';

$storageDir = '/var/www/xhprof-inbox/storage';

function scanXhprofFiles(string $dir): array
{
    $groups = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile() || !str_ends_with($file->getFilename(), '.xhprof') || str_ends_with($file->getFilename(), '.xhprof.html') || str_ends_with($file->getFilename(), '.xhprof.svg')) {
            continue;
        }

        $pathname = $file->getPathname();
        $relative = substr($pathname, strlen(rtrim($dir, '/')) + 1);
        $group = dirname($relative);
        if ($group === '.') {
            $group = '(root)';
        }

        $groups[$group][] = [
            'path' => $pathname,
            'relative' => $relative,
            'name' => $file->getFilename(),
            'size' => $file->getSize(),
            'mtime' => $file->getMTime(),
            'has_svg' => file_exists($pathname . '.svg'),
            'has_raw' => true,
        ];
    }

    ksort($groups);
    foreach ($groups as $group => &$files) {
        usort($files, fn (array $a, array $b) => $b['mtime'] <=> $a['mtime']);
    }
    unset($files);

    return $groups;
}

function getProfileSummary(string $path): ?array
{
    return loadProfileSummary($path);
}

$groups = scanXhprofFiles($storageDir);

$totalFiles = array_sum(array_map('count', $groups));
$totalSize = 0;
foreach ($groups as $files) {
    foreach ($files as $f) {
        $totalSize += $f['size'];
    }
}

$groupCount = count($groups);

function getSubdomainLabel(string $group): string
{
    $parts = explode('/', $group);
    return implode(' / ', array_map('htmlspecialchars', $parts));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>XHProf Inbox</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,Ubuntu,sans-serif;background:#0d1117;color:#e6edf3;min-height:100vh}
.header{background:#161b22;border-bottom:1px solid #30363d;padding:20px 32px}
.header h1{font-size:24px;color:#f0f6fc;display:flex;align-items:center;gap:10px}
.header h1 span{color:#8b949e;font-size:14px;font-weight:400}
.stats{display:flex;gap:24px;margin-top:8px;font-size:13px;color:#8b949e}
.stats strong{color:#e6edf3}
.container{max-width:1200px;margin:0 auto;padding:24px 32px}
.group-card{background:#161b22;border:1px solid #30363d;border-radius:8px;margin-bottom:16px;overflow:hidden}
.group-header{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;cursor:pointer;user-select:none;transition:background .15s}
.group-header:hover{background:#1c2128}
.group-header h2{font-size:15px;color:#f0f6fc;font-weight:600}
.group-header .badge{background:#1f6feb;color:#fff;font-size:11px;font-weight:600;padding:2px 10px;border-radius:10px;margin-left:10px}
.group-header .toggle{color:#8b949e;font-size:14px;transition:transform .2s}
.group-header.collapsed .toggle{transform:rotate(-90deg)}
.group-body{padding:0 20px 16px}
.group-body.hidden{display:none}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;padding:8px 12px;color:#8b949e;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #21262d;white-space:nowrap}
td{padding:8px 12px;border-bottom:1px solid #21262d;white-space:nowrap}
tr:last-child td{border-bottom:none}
tr:hover td{background:#1c2128}
.file-name{color:#e6edf3;font-family:"SF Mono","JetBrains Mono","Consolas",monospace;font-size:12px;word-break:break-all;max-width:400px}
.file-meta{color:#8b949e;font-size:11px}
.file-meta span{margin-right:12px}
.actions{display:flex;gap:6px}
.btn{display:inline-flex;align-items:center;gap:5px;padding:4px 12px;border-radius:6px;font-size:12px;font-weight:500;text-decoration:none;border:1px solid transparent;cursor:pointer;transition:all .15s}
.btn-xhgui{background:#238636;color:#fff;border-color:#2ea043}
.btn-xhgui:hover{background:#2ea043}
.btn-graphviz{background:#9e6a03;color:#fff;border-color:#bb8009}
.btn-graphviz:hover{background:#bb8009}
.btn-svg{background:#6e40c9;color:#fff;border-color:#8256d0}
.btn-svg:hover{background:#8256d0}
.subdomain-path{color:#58a6ff;font-family:"SF Mono","JetBrains Mono","Consolas",monospace;font-size:11px;margin-left:8px}
.empty-state{text-align:center;padding:64px 32px;color:#8b949e}
.empty-state h2{font-size:20px;color:#f0f6fc;margin-bottom:8px}
.empty-state p{font-size:14px;line-height:1.6}
.search-bar{margin-bottom:20px}
.search-bar input{width:100%;padding:10px 16px;background:#0d1117;border:1px solid #30363d;border-radius:8px;color:#e6edf3;font-size:14px;font-family:"SF Mono","JetBrains Mono","Consolas",monospace}
.search-bar input:focus{border-color:#58a6ff;outline:none}
.file-check{display:inline-flex;align-items:center;gap:4px;font-size:11px;color:#8b949e}
.file-check.ok{color:#3fb950}
.floating-toolbar{position:fixed;bottom:24px;right:24px;z-index:100}
.btn-all{background:#1f6feb;color:#fff;padding:10px 20px;border-radius:8px;font-size:13px;border:none;cursor:pointer;box-shadow:0 4px 12px rgba(0,0,0,.4);transition:all .15s}
.btn-all:hover{background:#388bfd}
@media(max-width:768px){.container{padding:12px 16px}.header{padding:16px}.file-name{max-width:200px}.actions{gap:4px}.btn{font-size:11px;padding:3px 8px}td,th{padding:6px 8px}}
</style>
</head>
<body>

<div class="header">
  <h1>XHProf Inbox <span>— <?= $groupCount ?> groups, <?= $totalFiles ?> profiles</span></h1>
  <div class="stats">
    <span>Groups: <strong><?= $groupCount ?></strong></span>
    <span>Profiles: <strong><?= $totalFiles ?></strong></span>
    <span>Total size: <strong><?= formatMemory($totalSize) ?></strong></span>
    <span>Storage: <strong>/var/www/xhprof-inbox/storage/</strong></span>
  </div>
</div>

<div class="container">
  <div class="search-bar">
    <input type="text" id="search" placeholder="Filter profiles by name, group, or function..." autofocus>
  </div>

  <?php if (empty($groups)): ?>
  <div class="empty-state">
    <h2>No profiles found</h2>
    <p>Place <code>.xhprof</code> files in <code>storage/</code> directory.<br>
    The scanner looks for <code>*.xhprof</code> files recursively and groups them by subdirectory.</p>
  </div>
  <?php else: ?>
  <div id="groups">
    <?php foreach ($groups as $group => $files): ?>
    <div class="group-card" data-group="<?= htmlspecialchars($group) ?>">
      <div class="group-header" onclick="toggleGroup(this)">
        <div>
          <h2>📁 <?= getSubdomainLabel($group) ?> <span class="badge"><?= count($files) ?></span></h2>
        </div>
        <span class="toggle">▼</span>
      </div>
      <div class="group-body">
        <table>
          <thead>
            <tr>
              <th>File</th>
              <th>Size</th>
              <th>Modified</th>
              <th>Wall Time</th>
              <th>Functions</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($files as $file):
                $summary = getProfileSummary($file['path']);
                $fileUrl = implode('/', array_map('rawurlencode', explode('/', $file['relative'])));
                $fileAttr = htmlspecialchars($file['relative']);
                ?>
            <tr class="file-row" data-name="<?= htmlspecialchars($file['name']) ?>" data-path="<?= $fileAttr ?>">
              <td>
                <div class="file-name"><?= htmlspecialchars($file['name']) ?></div>
                <div class="file-meta">
                  <?php if ($file['has_svg']): ?><span class="file-check ok">✓ SVG</span><?php endif; ?>
                </div>
              </td>
              <td class="file-meta"><?= formatMemory($file['size']) ?></td>
              <td class="file-meta"><?= date('Y-m-d H:i:s', $file['mtime']) ?></td>
              <td class="file-meta"><?= $summary ? formatMicroseconds($summary['wt']) : '—' ?></td>
              <td class="file-meta"><?= $summary ? number_format($summary['ct']) : '—' ?></td>
              <td>
                <div class="actions">
                  <a href="xhgui.php?file=<?= $fileUrl ?>" class="btn btn-xhgui" title="Open in XHGui">📊 XHGui</a>
                  <a href="graphviz.php?file=<?= $fileUrl ?>" class="btn btn-graphviz" title="Open in Graphviz">🔀 Graphviz</a>
                  <?php if ($file['has_svg']): ?>
                  <a href="storage/<?= $fileUrl ?>.svg" class="btn btn-svg" title="Open SVG flamegraph" target="_blank">SVG</a>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<script>
function toggleGroup(el) {
    el.classList.toggle('collapsed');
    var body = el.nextElementSibling;
    body.classList.toggle('hidden');
}

document.getElementById('search').addEventListener('input', function () {
    var q = this.value.toLowerCase();
    document.querySelectorAll('.file-row').forEach(function (row) {
        var name = row.getAttribute('data-name').toLowerCase();
        var path = row.getAttribute('data-path').toLowerCase();
        var match = name.indexOf(q) !== -1 || path.indexOf(q) !== -1;
        row.style.display = match ? '' : 'none';
    });

    document.querySelectorAll('.group-card').forEach(function (card) {
        var visible = Array.from(card.querySelectorAll('.file-row')).some(function (r) {
            return r.style.display !== 'none';
        });
        card.style.display = visible ? '' : 'none';
    });
});
</script>

</body>
</html>
