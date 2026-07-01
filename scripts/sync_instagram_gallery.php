<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (!class_exists('PDO')) {
    fwrite(STDERR, 'Error sincronizando Instagram: la version PHP de esta tarea no tiene PDO activo. Activa PDO/pdo_mysql o selecciona otra version PHP en la tarea programada.' . PHP_EOL);
    exit(1);
}

if (!extension_loaded('pdo_mysql')) {
    fwrite(STDERR, 'Error sincronizando Instagram: la version PHP de esta tarea no tiene pdo_mysql activo. Activa pdo_mysql o selecciona otra version PHP en la tarea programada.' . PHP_EOL);
    exit(1);
}

function burnout_instagram_sync_config(): array
{
    $config = burnout_env_config();
    $instagramConfig = $config['instagram'] ?? [];
    $resolvedConfig = $instagramConfig + [
        'enabled' => false,
        'graph_version' => 'v20.0',
        'user_id' => '',
        'access_token' => '',
        'limit' => 12,
        'sync_limit' => 24,
        'sync_max_pages' => 20,
        'cache_keep_items' => 36,
        'max_image_bytes' => 2000000,
        'profile_url' => 'https://www.instagram.com/burnoutairsoft/',
        'ca_file' => '',
        'ssl_verify' => true,
        'refresh_enabled' => false,
        'refresh_url' => 'https://graph.instagram.com/refresh_access_token',
        'refresh_token_param' => 'access_token',
        'refresh_params' => [
            'grant_type' => 'ig_refresh_token',
        ],
        'refresh_interval_days' => 30,
    ];
    $caFile = getenv('BURNOUT_INSTAGRAM_CA_FILE');
    $sslVerify = getenv('BURNOUT_INSTAGRAM_SSL_VERIFY');
    $maxImageBytes = getenv('BURNOUT_INSTAGRAM_MAX_IMAGE_BYTES');

    if (is_string($caFile) && trim($caFile) !== '') {
        $resolvedConfig['ca_file'] = trim($caFile);
    }

    if (is_string($sslVerify) && trim($sslVerify) !== '') {
        $resolvedConfig['ssl_verify'] = !in_array(strtolower(trim($sslVerify)), ['0', 'false', 'no', 'off'], true);
    }

    if (is_string($maxImageBytes) && trim($maxImageBytes) !== '') {
        $resolvedConfig['max_image_bytes'] = max(100000, (int) $maxImageBytes);
    }

    return $resolvedConfig;
}

function burnout_instagram_sync_tables(PDO $pdo): void
{
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

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS instagram_cache_state (
          state_key VARCHAR(80) NOT NULL,
          state_value MEDIUMTEXT NOT NULL,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (state_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function burnout_instagram_sync_state_get(PDO $pdo, string $key): string
{
    $statement = $pdo->prepare('SELECT state_value FROM instagram_cache_state WHERE state_key = :state_key LIMIT 1');
    $statement->execute(['state_key' => $key]);
    $value = $statement->fetchColumn();

    return is_string($value) ? $value : '';
}

function burnout_instagram_sync_state_set(PDO $pdo, string $key, string $value): void
{
    $statement = $pdo->prepare(
        'INSERT INTO instagram_cache_state (state_key, state_value)
         VALUES (:state_key, :state_value)
         ON DUPLICATE KEY UPDATE state_value = VALUES(state_value)'
    );
    $statement->execute([
        'state_key' => $key,
        'state_value' => $value,
    ]);
}

function burnout_instagram_sync_access_token(PDO $pdo, array $config): string
{
    $storedToken = burnout_instagram_sync_state_get($pdo, 'access_token');

    return $storedToken !== '' ? $storedToken : trim((string) ($config['access_token'] ?? ''));
}

function burnout_instagram_sync_refresh_token_if_needed(PDO $pdo, array $config, string $accessToken): string
{
    if (empty($config['refresh_enabled']) || $accessToken === '') {
        return $accessToken;
    }

    $lastRefresh = burnout_instagram_sync_state_get($pdo, 'access_token_refreshed_at');
    $intervalDays = max(1, (int) ($config['refresh_interval_days'] ?? 30));

    if ($lastRefresh !== '') {
        try {
            $last = new DateTimeImmutable($lastRefresh);

            if ($last->modify('+' . $intervalDays . ' days') > new DateTimeImmutable('now')) {
                return $accessToken;
            }
        } catch (Throwable $exception) {
            // Invalid stored date: refresh below.
        }
    }

    $url = (string) ($config['refresh_url'] ?? 'https://graph.instagram.com/refresh_access_token');
    $params = is_array($config['refresh_params'] ?? null) ? $config['refresh_params'] : [];
    $tokenParam = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($config['refresh_token_param'] ?? 'access_token')) ?: 'access_token';
    $params[$tokenParam] = $accessToken;
    $separator = str_contains($url, '?') ? '&' : '?';
    $response = burnout_instagram_sync_http_get($url . $separator . http_build_query($params), $config);
    $decoded = json_decode($response['body'], true);

    if (!is_array($decoded) || empty($decoded['access_token'])) {
        throw new RuntimeException('No se ha podido refrescar el token de Instagram.');
    }

    $newToken = (string) $decoded['access_token'];
    burnout_instagram_sync_state_set($pdo, 'access_token', $newToken);
    burnout_instagram_sync_state_set($pdo, 'access_token_refreshed_at', (new DateTimeImmutable('now'))->format(DATE_ATOM));

    if (isset($decoded['expires_in'])) {
        burnout_instagram_sync_state_set($pdo, 'access_token_expires_in', (string) $decoded['expires_in']);
    }

    return $newToken;
}

function burnout_instagram_sync_graph_url(array $config, string $accessToken, int $limit, string $after = ''): string
{
    $version = preg_replace('/[^A-Za-z0-9._-]/', '', (string) ($config['graph_version'] ?? 'v20.0')) ?: 'v20.0';
    $userId = trim((string) ($config['user_id'] ?? ''));
    $queryParams = [
        'fields' => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp',
        'limit' => max(1, min(50, $limit)),
        'access_token' => $accessToken,
    ];

    if ($after !== '') {
        $queryParams['after'] = $after;
    }

    return sprintf(
        'https://graph.facebook.com/%s/%s/media?%s',
        $version,
        rawurlencode($userId),
        http_build_query($queryParams)
    );
}

function burnout_instagram_sync_fetch_media(array $config, string $accessToken): array
{
    $userId = trim((string) ($config['user_id'] ?? ''));

    if ($userId === '' || $accessToken === '') {
        throw new RuntimeException('Falta configurar user_id o access_token de Instagram.');
    }

    $configuredLimit = (int) ($config['sync_limit'] ?? $config['limit'] ?? 24);
    $syncAll = $configuredLimit <= 0;
    $target = $syncAll ? PHP_INT_MAX : max(1, min(500, $configuredLimit));
    $maxPages = max(1, min(50, (int) ($config['sync_max_pages'] ?? 20)));
    $items = [];
    $after = '';
    $page = 0;

    while (count($items) < $target && $page < $maxPages) {
        $page++;
        $pageLimit = $syncAll ? 50 : min(50, $target - count($items));
        $response = burnout_instagram_sync_http_get(
            burnout_instagram_sync_graph_url($config, $accessToken, $pageLimit, $after),
            $config
        );
        $decoded = json_decode($response['body'], true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Instagram Graph API ha devuelto una respuesta no valida.');
        }

        if (isset($decoded['error'])) {
            $message = is_array($decoded['error']) ? (string) ($decoded['error']['message'] ?? 'Error de Instagram Graph API.') : 'Error de Instagram Graph API.';
            throw new RuntimeException($message);
        }

        foreach (($decoded['data'] ?? []) as $item) {
            if (is_array($item)) {
                $items[] = $item;
            }
        }

        $after = is_array($decoded['paging']['cursors'] ?? null) ? (string) ($decoded['paging']['cursors']['after'] ?? '') : '';

        if ($after === '' || empty($decoded['paging']['next'])) {
            break;
        }
    }

    return $syncAll ? $items : array_slice($items, 0, $target);
}

function burnout_instagram_sync_http_get(string $url, array $config): array
{
    if (function_exists('curl_init')) {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException('No se ha podido iniciar la conexion HTTP.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Accept: */*'],
        ]);

        $caFile = trim((string) ($config['ca_file'] ?? ''));

        if ($caFile !== '' && is_file($caFile)) {
            curl_setopt($handle, CURLOPT_CAINFO, $caFile);
        }

        if (($config['ssl_verify'] ?? true) === false) {
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $body = curl_exec($handle);
        $error = curl_error($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        curl_close($handle);

        if ($body === false || $statusCode >= 400) {
            throw new RuntimeException('Error HTTP al conectar con Instagram' . ($error !== '' ? ': ' . $error : '.'));
        }

        return [
            'body' => (string) $body,
            'content_type' => $contentType,
        ];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
            'header' => "Accept: */*\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $context);

    if ($body === false) {
        $lastError = error_get_last();
        $message = is_array($lastError) ? (string) ($lastError['message'] ?? '') : '';

        throw new RuntimeException('Error HTTP al conectar con Instagram' . ($message !== '' ? ': ' . $message : '.'));
    }

    $contentType = '';

    foreach (($http_response_header ?? []) as $header) {
        if (stripos($header, 'Content-Type:') === 0) {
            $contentType = trim(substr($header, strlen('Content-Type:')));
            break;
        }
    }

    return [
        'body' => (string) $body,
        'content_type' => $contentType,
    ];
}

function burnout_instagram_sync_image_url(array $item): string
{
    $thumbnailUrl = trim((string) ($item['thumbnail_url'] ?? ''));
    $mediaUrl = trim((string) ($item['media_url'] ?? ''));

    return $thumbnailUrl !== '' ? $thumbnailUrl : $mediaUrl;
}

function burnout_instagram_sync_timestamp(?string $timestamp): ?string
{
    if (!$timestamp) {
        return null;
    }

    try {
        return (new DateTimeImmutable($timestamp))->format('Y-m-d H:i:s');
    } catch (Throwable $exception) {
        return null;
    }
}

function burnout_instagram_sync_store_item(PDO $pdo, array $config, array $item): bool
{
    $instagramId = trim((string) ($item['id'] ?? ''));
    $permalink = trim((string) ($item['permalink'] ?? ''));
    $imageUrl = burnout_instagram_sync_image_url($item);

    if ($instagramId === '' || $permalink === '' || $imageUrl === '') {
        return false;
    }

    $image = burnout_instagram_sync_http_get($imageUrl, $config);
    $body = $image['body'];
    $size = strlen($body);
    $maxBytes = max(100000, (int) ($config['max_image_bytes'] ?? 2000000));

    if ($size <= 0 || $size > $maxBytes) {
        throw new RuntimeException('Imagen omitida por tamano no valido: ' . $instagramId);
    }

    $mime = strtolower(trim(explode(';', (string) ($image['content_type'] ?? ''))[0]));

    if (!preg_match('/^image\/[a-z0-9.+-]+$/', $mime)) {
        $mime = 'image/jpeg';
    }

    $statement = $pdo->prepare(
        'INSERT INTO instagram_cache_items (
            instagram_id, caption, media_type, permalink, published_at,
            image_mime, image_data, image_size, image_hash, is_visible, fetched_at
         )
         VALUES (
            :instagram_id, :caption, :media_type, :permalink, :published_at,
            :image_mime, :image_data, :image_size, :image_hash, 1, CURRENT_TIMESTAMP
         )
         ON DUPLICATE KEY UPDATE
            caption = VALUES(caption),
            media_type = VALUES(media_type),
            permalink = VALUES(permalink),
            published_at = VALUES(published_at),
            image_mime = VALUES(image_mime),
            image_data = VALUES(image_data),
            image_size = VALUES(image_size),
            image_hash = VALUES(image_hash),
            is_visible = 1,
            fetched_at = CURRENT_TIMESTAMP'
    );
    $statement->execute([
        'instagram_id' => $instagramId,
        'caption' => trim((string) ($item['caption'] ?? '')),
        'media_type' => trim((string) ($item['media_type'] ?? '')),
        'permalink' => $permalink,
        'published_at' => burnout_instagram_sync_timestamp((string) ($item['timestamp'] ?? '')),
        'image_mime' => $mime,
        'image_data' => $body,
        'image_size' => $size,
        'image_hash' => hash('sha256', $body),
    ]);

    return true;
}

function burnout_instagram_sync_prune(PDO $pdo, int $keepItems): void
{
    if ($keepItems <= 0) {
        return;
    }

    $keepItems = max(1, min(200, $keepItems));
    $pdo->exec(
        'DELETE FROM instagram_cache_items
         WHERE id NOT IN (
           SELECT id FROM (
             SELECT id
             FROM instagram_cache_items
             ORDER BY COALESCE(published_at, created_at) DESC, id DESC
             LIMIT ' . $keepItems . '
           ) AS keep_items
         )'
    );
}

try {
    $config = burnout_instagram_sync_config();
    $pdo = burnout_pdo();
    burnout_instagram_sync_tables($pdo);

    if (empty($config['enabled'])) {
        throw new RuntimeException('La sincronizacion de Instagram esta desactivada en la configuracion.');
    }

    $accessToken = burnout_instagram_sync_access_token($pdo, $config);
    $accessToken = burnout_instagram_sync_refresh_token_if_needed($pdo, $config, $accessToken);
    $items = burnout_instagram_sync_fetch_media($config, $accessToken);
    $saved = 0;

    foreach ($items as $item) {
        try {
            if (burnout_instagram_sync_store_item($pdo, $config, $item)) {
                $saved++;
            }
        } catch (Throwable $itemException) {
            fwrite(STDERR, 'Publicacion omitida: ' . $itemException->getMessage() . PHP_EOL);
        }
    }

    burnout_instagram_sync_prune($pdo, (int) ($config['cache_keep_items'] ?? 36));
    burnout_instagram_sync_state_set($pdo, 'last_sync_at', (new DateTimeImmutable('now'))->format(DATE_ATOM));
    burnout_instagram_sync_state_set($pdo, 'last_sync_count', (string) $saved);

    echo 'Instagram sincronizado. Publicaciones guardadas: ' . $saved . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error sincronizando Instagram: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
