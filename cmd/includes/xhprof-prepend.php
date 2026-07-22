<?php

declare(strict_types=1);

$flags = 0;
$envFlags = explode(',', strtoupper($_SERVER['XHPROF_FLAGS'] ?? 'CPU,MEMORY'));

foreach ($envFlags as $f) {
    $flags |= match (trim($f)) {
        'CPU' => XHPROF_FLAGS_CPU,
        'MEMORY' => XHPROF_FLAGS_MEMORY,
        'NO_BUILTINS' => XHPROF_FLAGS_NO_BUILTINS,
        default => 0,
    };
}
if ($flags === 0) {
    $flags = XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY;
}

xhprof_enable($flags);

$outputDir = $_SERVER['XHPROF_OUTPUT'] ?? ($_SERVER['XHPROF_PROJECT_DIR'] ?? '/tmp') . '/storage/app/tmp/xhprof';

register_shutdown_function(static function () use ($outputDir): void {
    $data = xhprof_disable();
    if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
        fwrite(STDERR, "XHProf: Cannot create $outputDir\n");
        return;
    }
    $runId = date('Y-m-d_H-i-s') . '-' . bin2hex(random_bytes(4));
    $script = basename($_SERVER['SCRIPT_FILENAME'] ?? 'unknown');
    $path = "$outputDir/$runId.$script.xhprof";
    file_put_contents($path, serialize($data));
    fwrite(STDERR, "\n--- XHProf profile saved: $path\n");
});
