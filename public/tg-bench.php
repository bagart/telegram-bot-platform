<?php

declare(strict_types=1);

$sleep = max(0, (int)($_GET['sleep'] ?? 300_000));
$bodySize = max(0, (int)($_GET['body_size'] ?? 0));
$fragment = max(0, (int)($_GET['fragment'] ?? 0));

usleep($sleep);

header('Content-Type: application/json');

if ($bodySize > 0) {
    $body = str_repeat('x', $bodySize);
    $payload = '{"ok":true,"result":{"message_id":1,"data":"' . $body . '"}}';
} else {
    $payload = '{"ok":true,"result":{"message_id":1,"text":"Benchmark"}}';
}

if ($fragment > 0) {
    // Disable output buffering for true streaming
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Length: ' . strlen($payload));
    header('X-Accel-Buffering: no');
    echo 'HTTP/1.1 200 OK' . "\r\n";
    echo 'Content-Type: application/json' . "\r\n";
    echo 'Content-Length: ' . strlen($payload) . "\r\n";
    echo 'X-Accel-Buffering: no' . "\r\n";
    echo "\r\n";
    flush();

    $chunkSize = max(1, $fragment);
    $offset = 0;
    $len = strlen($payload);
    while ($offset < $len) {
        $chunk = substr($payload, $offset, $chunkSize);
        echo $chunk;
        flush();
        $offset += $chunkSize;
        // Small delay between chunks to simulate slow network
        usleep(100);
    }
} else {
    echo $payload;
}
