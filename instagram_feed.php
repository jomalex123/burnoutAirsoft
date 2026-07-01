<?php

declare(strict_types=1);

require_once __DIR__ . '/config/env_loader.php';
require_once __DIR__ . '/config/database.php';

function burnout_instagram_config(): array
{
    $config = burnout_env_config();
    $instagramConfig = $config['instagram'] ?? [];

    return $instagramConfig + [
        'enabled' => false,
        'graph_version' => 'v20.0',
        'user_id' => '',
        'access_token' => '',
        'limit' => 12,
        'fallback_file' => __DIR__ . '/assets/data/instagram_gallery.json',
        'profile_url' => 'https://www.instagram.com/burnoutairsoft/',
        'ca_file' => '',
        'ssl_verify' => true,
    ];
}

function burnout_instagram_request_limit(array $config): int
{
    $configuredLimit = (int) ($config['limit'] ?? 12);
    $requestedLimit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);
    $limit = $requestedLimit !== false && $requestedLimit !== null ? $requestedLimit : $configuredLimit;

    return max(1, min(50, $limit));
}

function burnout_instagram_request_after(): string
{
    $after = filter_input(INPUT_GET, 'after', FILTER_UNSAFE_RAW);

    if (!is_string($after)) {
        return '';
    }

    return preg_replace('/[^A-Za-z0-9._=-]/', '', $after) ?? '';
}

function burnout_instagram_fallback(array $config): array
{
    $fallbackFile = (string) ($config['fallback_file'] ?? '');

    if ($fallbackFile !== '' && is_file($fallbackFile)) {
        $contents = file_get_contents($fallbackFile);
        $decoded = is_string($contents) ? json_decode($contents, true) : null;

        if (is_array($decoded)) {
            $allItems = array_values(is_array($decoded['items'] ?? null) ? $decoded['items'] : []);
            $limit = burnout_instagram_request_limit($config);
            $offset = max(0, (int) burnout_instagram_request_after());
            $items = array_slice($allItems, $offset, $limit);
            $nextOffset = $offset + count($items);

            return [
                'profileUrl' => (string) ($decoded['profileUrl'] ?? $config['profile_url'] ?? 'https://www.instagram.com/burnoutairsoft/'),
                'items' => $items,
                'pagination' => [
                    'hasMore' => $nextOffset < count($allItems),
                    'nextCursor' => $nextOffset < count($allItems) ? (string) $nextOffset : '',
                ],
            ];
        }
    }

    return [
        'profileUrl' => (string) ($config['profile_url'] ?? 'https://www.instagram.com/burnoutairsoft/'),
        'items' => [],
        'pagination' => [
            'hasMore' => false,
            'nextCursor' => '',
        ],
    ];
}

function burnout_instagram_cache_tables(PDO $pdo): void
{
    static $ready = false;

    if ($ready) {
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS instagram_cache_items (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          instagram_id VARCHAR(80) NOT NULL,
          caption TEXT DEFAULT NULL,
          media_type VARCHAR(40) DEFAULT NULL,
          permalink VARCHAR(500) NOT NULL,
          published_at DATETIME DEFAULT NULL,
          image_mime VARCHAR(80) NOT NULL DEFAULT "image/jpeg",
          image_data MEDIUMBLOB NOT NULL,
          image_size INT UNSIGNED NOT NULL DEFAULT 0,
          image_hash CHAR(64) DEFAULT NULL,
          is_visible TINYINT(1) NOT NULL DEFAULT 1,
          fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY instagram_cache_items_instagram_id_unique (instagram_id),
          KEY instagram_cache_items_visible_published_index (is_visible, published_at),
          KEY instagram_cache_items_fetched_at_index (fetched_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $ready = true;
}

function burnout_instagram_cache_payload(array $config): array
{
    $pdo = burnout_pdo();
    burnout_instagram_cache_tables($pdo);

    $limit = burnout_instagram_request_limit($config);
    $offset = max(0, (int) burnout_instagram_request_after());
    $statement = $pdo->prepare(
        'SELECT instagram_id, caption, media_type, permalink, published_at, image_hash
         FROM instagram_cache_items
         WHERE is_visible = 1
         ORDER BY COALESCE(published_at, created_at) DESC, id DESC
         LIMIT :limit OFFSET :offset'
    );
    $statement->bindValue('limit', $limit, PDO::PARAM_INT);
    $statement->bindValue('offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    $items = array_map('burnout_instagram_cache_map_item', $statement->fetchAll());

    $countStatement = $pdo->query('SELECT COUNT(*) FROM instagram_cache_items WHERE is_visible = 1');
    $total = (int) $countStatement->fetchColumn();
    $nextOffset = $offset + count($items);

    return [
        'profileUrl' => (string) ($config['profile_url'] ?? 'https://www.instagram.com/burnoutairsoft/'),
        'items' => $items,
        'pagination' => [
            'hasMore' => $nextOffset < $total,
            'nextCursor' => $nextOffset < $total ? (string) $nextOffset : '',
        ],
    ];
}

function burnout_instagram_cache_map_item(array $item): array
{
    $caption = trim((string) ($item['caption'] ?? ''));
    $publishedAt = (string) ($item['published_at'] ?? '');
    $imageUrl = 'instagram_image.php?id=' . rawurlencode((string) $item['instagram_id']);
    $hash = (string) ($item['image_hash'] ?? '');

    if ($hash !== '') {
        $imageUrl .= '&v=' . rawurlencode(substr($hash, 0, 12));
    }

    return [
        'id' => (string) $item['instagram_id'],
        'image' => $imageUrl,
        'url' => (string) $item['permalink'],
        'title' => 'Burnout Airsoft',
        'description' => $caption,
        'date' => burnout_instagram_format_date($publishedAt),
        'alt' => $caption !== '' ? burnout_instagram_excerpt($caption, 140) : 'Publicacion de Instagram de Burnout Airsoft',
        'type' => (string) ($item['media_type'] ?? ''),
    ];
}

function burnout_instagram_graph_url(array $config): string
{
    $version = preg_replace('/[^A-Za-z0-9._-]/', '', (string) ($config['graph_version'] ?? 'v20.0')) ?: 'v20.0';
    $userId = trim((string) ($config['user_id'] ?? ''));
    $queryParams = [
        'fields' => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp',
        'limit' => burnout_instagram_request_limit($config),
        'access_token' => (string) ($config['access_token'] ?? ''),
    ];
    $after = burnout_instagram_request_after();

    if ($after !== '') {
        $queryParams['after'] = $after;
    }

    $query = http_build_query($queryParams);

    return sprintf('https://graph.facebook.com/%s/%s/media?%s', $version, rawurlencode($userId), $query);
}

function burnout_instagram_fetch_graph(array $config): array
{
    $userId = trim((string) ($config['user_id'] ?? ''));
    $accessToken = trim((string) ($config['access_token'] ?? ''));

    if ($userId === '' || $accessToken === '') {
        throw new RuntimeException('Falta configurar user_id o access_token de Instagram.');
    }

    $response = burnout_instagram_http_get(burnout_instagram_graph_url($config));

    $decoded = json_decode($response, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('Instagram Graph API ha devuelto una respuesta no valida.');
    }

    if (isset($decoded['error'])) {
        $message = is_array($decoded['error']) ? (string) ($decoded['error']['message'] ?? 'Error de Instagram Graph API.') : 'Error de Instagram Graph API.';
        throw new RuntimeException($message);
    }

    $items = array_values(array_filter(array_map('burnout_instagram_map_item', $decoded['data'] ?? [])));
    $nextCursor = is_array($decoded['paging']['cursors'] ?? null) ? (string) ($decoded['paging']['cursors']['after'] ?? '') : '';

    return [
        'profileUrl' => (string) ($config['profile_url'] ?? 'https://www.instagram.com/burnoutairsoft/'),
        'items' => $items,
        'pagination' => [
            'hasMore' => isset($decoded['paging']['next']) && $nextCursor !== '',
            'nextCursor' => $nextCursor,
        ],
    ];
}

function burnout_instagram_http_get(string $url): string
{
    if (function_exists('curl_init')) {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException('No se ha podido iniciar la conexion con Instagram Graph API.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $caFile = trim((string) ($GLOBALS['burnout_instagram_ca_file'] ?? ''));

        if ($caFile !== '' && is_file($caFile)) {
            curl_setopt($handle, CURLOPT_CAINFO, $caFile);
        }

        if (($GLOBALS['burnout_instagram_ssl_verify'] ?? true) === false) {
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $response = curl_exec($handle);
        $error = curl_error($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($response === false || $statusCode >= 500) {
            throw new RuntimeException('No se ha podido conectar con Instagram Graph API' . ($error !== '' ? ': ' . $error : '.'));
        }

        return (string) $response;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'header' => "Accept: application/json\r\n",
        ],
    ]);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        $lastError = error_get_last();
        $message = is_array($lastError) ? (string) ($lastError['message'] ?? '') : '';

        throw new RuntimeException('No se ha podido conectar con Instagram Graph API' . ($message !== '' ? ': ' . $message : '.'));
    }

    return $response;
}

function burnout_instagram_map_item($item): ?array
{
    if (!is_array($item)) {
        return null;
    }

    $mediaUrl = (string) ($item['media_url'] ?? '');
    $thumbnailUrl = (string) ($item['thumbnail_url'] ?? '');
    $image = $thumbnailUrl !== '' ? $thumbnailUrl : $mediaUrl;
    $permalink = (string) ($item['permalink'] ?? '');

    if ($image === '' || $permalink === '') {
        return null;
    }

    $caption = trim((string) ($item['caption'] ?? ''));

    return [
        'id' => (string) ($item['id'] ?? ''),
        'image' => $image,
        'url' => $permalink,
        'title' => 'Burnout Airsoft',
        'description' => $caption,
        'date' => burnout_instagram_format_date((string) ($item['timestamp'] ?? '')),
        'alt' => $caption !== '' ? burnout_instagram_excerpt($caption, 140) : 'Publicacion de Instagram de Burnout Airsoft',
        'type' => (string) ($item['media_type'] ?? ''),
    ];
}

function burnout_instagram_excerpt(string $value, int $limit): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $limit, 'UTF-8');
    }

    return $value;
}

function burnout_instagram_format_date(string $timestamp): string
{
    if ($timestamp === '') {
        return '@burnoutairsoft';
    }

    try {
        $date = new DateTimeImmutable($timestamp);
    } catch (Throwable $exception) {
        return '@burnoutairsoft';
    }

    return $date->format('d/m/Y');
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$config = burnout_instagram_config();
$GLOBALS['burnout_instagram_ca_file'] = (string) ($config['ca_file'] ?? '');
$GLOBALS['burnout_instagram_ssl_verify'] = (bool) ($config['ssl_verify'] ?? true);

try {
    $payload = burnout_instagram_cache_payload($config);

    if (!$payload['items']) {
        $payload = !empty($config['enabled'])
            ? burnout_instagram_fetch_graph($config)
            : burnout_instagram_fallback($config);
    }
} catch (Throwable $exception) {
    error_log($exception->getMessage());

    try {
        $payload = burnout_instagram_cache_payload($config);

        if (!$payload['items']) {
            $payload = burnout_instagram_fallback($config);
        }
    } catch (Throwable $cacheException) {
        error_log($cacheException->getMessage());
        $payload = burnout_instagram_fallback($config);
    }
}

echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{"items":[]}';
