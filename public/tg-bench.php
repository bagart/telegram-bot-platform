<?php

declare(strict_types=1);

usleep((int)($_REQUEST['sleep'] ?? 300_000));
http_response_code(200);
header('Content-Type: application/json');

echo json_encode([
    'ok' => true,
    'result' => [
        'message_id' => random_int(1, 100000),
        'text' => (string)file_get_contents('php://input'),
    ],
]);
