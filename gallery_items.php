<?php

declare(strict_types=1);

require_once __DIR__ . '/config/gallery.php';

header('Content-Type: application/json; charset=utf-8');

try {
    echo json_encode(burnout_gallery_all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    echo json_encode([]);
}
