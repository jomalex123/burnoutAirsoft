<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

$imageId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($imageId === false || $imageId === null || $imageId <= 0) {
    http_response_code(404);
    exit;
}

try {
    $statement = burnout_pdo()->prepare(
        'SELECT
            gei.image_mime,
            gei.image_data,
            gei.image_hash,
            gei.updated_at,
            ge.is_visible
         FROM gallery_event_images gei
         INNER JOIN gallery_events ge ON ge.id = gei.gallery_event_id
         WHERE gei.id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $imageId]);
    $image = $statement->fetch();

    if (!$image || !is_string($image['image_data'] ?? null) || $image['image_data'] === '') {
        http_response_code(404);
        exit;
    }

    if (!$image['is_visible'] && !burnout_gallery_image_admin_can_preview()) {
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
    header('Cache-Control: ' . ($image['is_visible'] ? 'public, max-age=604800, immutable' : 'private, no-store'));
    header('ETag: ' . $etag);

    echo $image['image_data'];
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(404);
}

function burnout_gallery_image_admin_can_preview(): bool
{
    try {
        require_once __DIR__ . '/config/auth.php';

        return burnout_current_admin() !== null;
    } catch (Throwable $exception) {
        error_log($exception->getMessage());

        return false;
    }
}
