<?php

declare(strict_types=1);

require_once __DIR__ . '/config/env_loader.php';

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
    ];
}

function burnout_instagram_fallback(array $config): array
{
    $fallbackFile = (string) ($config['fallback_file'] ?? '');

    if ($fallbackFile !== '' && is_file($fallbackFile)) {
        $contents = file_get_contents($fallbackFile);
        $decoded = is_string($contents) ? json_decode($contents, true) : null;

        if (is_array($decoded)) {
            return $decoded + [
                'profileUrl' => (string) ($config['profile_url'] ?? 'https://www.instagram.com/burnoutairsoft/'),
                'items' => [],
            ];
        }
    }

    return [
        'profileUrl' => (string) ($config['profile_url'] ?? 'https://www.instagram.com/burnoutairsoft/'),
        'items' => [],
    ];
}

function burnout_instagram_graph_url(array $config): string
{
    $version = preg_replace('/[^A-Za-z0-9._-]/', '', (string) ($config['graph_version'] ?? 'v20.0')) ?: 'v20.0';
    $userId = trim((string) ($config['user_id'] ?? ''));
    $limit = max(1, min(50, (int) ($config['limit'] ?? 12)));
    $query = http_build_query([
        'fields' => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp',
        'limit' => $limit,
        'access_token' => (string) ($config['access_token'] ?? ''),
    ]);

    return sprintf('https://graph.facebook.com/%s/%s/media?%s', $version, rawurlencode($userId), $query);
}

function burnout_instagram_fetch_graph(array $config): array
{
    $userId = trim((string) ($config['user_id'] ?? ''));
    $accessToken = trim((string) ($config['access_token'] ?? ''));

    if ($userId === '' || $accessToken === '') {
        throw new RuntimeException('Falta configurar user_id o access_token de Instagram.');
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'header' => "Accept: application/json\r\n",
        ],
    ]);
    $response = @file_get_contents(burnout_instagram_graph_url($config), false, $context);

    if ($response === false) {
        throw new RuntimeException('No se ha podido conectar con Instagram Graph API.');
    }

    $decoded = json_decode($response, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('Instagram Graph API ha devuelto una respuesta no valida.');
    }

    if (isset($decoded['error'])) {
        $message = is_array($decoded['error']) ? (string) ($decoded['error']['message'] ?? 'Error de Instagram Graph API.') : 'Error de Instagram Graph API.';
        throw new RuntimeException($message);
    }

    $items = array_values(array_filter(array_map('burnout_instagram_map_item', $decoded['data'] ?? [])));

    return [
        'profileUrl' => (string) ($config['profile_url'] ?? 'https://www.instagram.com/burnoutairsoft/'),
        'items' => $items,
    ];
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
        'alt' => $caption !== '' ? substr($caption, 0, 140) : 'Publicacion de Instagram de Burnout Airsoft',
        'type' => (string) ($item['media_type'] ?? ''),
    ];
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

try {
    $payload = !empty($config['enabled'])
        ? burnout_instagram_fetch_graph($config)
        : burnout_instagram_fallback($config);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    $payload = burnout_instagram_fallback($config);
}

echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{"items":[]}';
