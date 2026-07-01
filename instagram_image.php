<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

function burnout_instagram_image_id(): string
{
    $id = filter_input(INPUT_GET, 'id', FILTER_UNSAFE_RAW);

    if (!is_string($id)) {
        return '';
    }

    return preg_replace('/[^A-Za-z0-9_-]/', '', $id) ?? '';
}

$instagramId = burnout_instagram_image_id();

if ($instagramId === '') {
    http_response_code(404);
    exit;
}

try {
    $statement = burnout_pdo()->prepare(
        'SELECT instagram_id, image_mime, image_data, image_hash, updated_at
         FROM instagram_cache_items
         WHERE instagram_id = :instagram_id AND is_visible = 1
         LIMIT 1'
    );
    $statement->execute(['instagram_id' => $instagramId]);
    $image = $statement->fetch();

    if (!$image || !is_string($image['image_data'] ?? null) || $image['image_data'] === '') {
        http_response_code(404);
        exit;
    }

    $mime = preg_match('/^image\/[A-Za-z0-9.+-]+$/', (string) ($image['image_mime'] ?? ''))
        ? (string) $image['image_mime']
        : 'image/jpeg';
    $etag = '"' . sha1((string) ($image['image_hash'] ?? '') . (string) ($image['updated_at'] ?? '')) . '"';

    if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        exit;
    }

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen((string) $image['image_data']));
    header('Cache-Control: public, max-age=604800, immutable');
    header('ETag: ' . $etag);

    echo $image['image_data'];
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(404);
}
