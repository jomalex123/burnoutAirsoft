<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

function burnout_gallery_all(): array
{
    $statement = burnout_pdo()->query(
        'SELECT id, src, alt, description
         FROM gallery
         ORDER BY id ASC'
    );

    return array_map(static function (array $item): array {
        return [
            'id' => (int) $item['id'],
            'src' => (string) $item['src'],
            'alt' => (string) $item['alt'],
            'description' => (string) ($item['description'] ?? ''),
        ];
    }, $statement->fetchAll());
}

function burnout_gallery_add(string $src, string $alt, string $description): void
{
    $src = burnout_gallery_validate_src($src);

    $statement = burnout_pdo()->prepare(
        'INSERT INTO gallery (src, alt, description)
         VALUES (:src, :alt, :description)'
    );

    $statement->execute([
        'src' => $src,
        'alt' => $alt,
        'description' => $description,
    ]);
}

function burnout_gallery_validate_src(string $src): string
{
    $src = trim($src);

    if ($src === '') {
        throw new RuntimeException('La ruta de imagen es obligatoria.');
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $src)) {
        throw new RuntimeException('La ruta de imagen no es valida.');
    }

    if (str_starts_with($src, 'https://scontent.')) {
        if (!filter_var($src, FILTER_VALIDATE_URL) || parse_url($src, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('La URL externa de imagen no es valida.');
        }

        return $src;
    }

    $normalized = str_replace('\\', '/', $src);

    if ($normalized !== $src || !str_starts_with($normalized, 'images/gallery/')) {
        throw new RuntimeException('Las imagenes locales deben estar dentro de images/gallery/.');
    }

    if (str_contains($normalized, '../') || str_contains($normalized, '/..') || str_starts_with($normalized, '/')) {
        throw new RuntimeException('La ruta de imagen no puede salir de images/gallery/.');
    }

    if (!preg_match('/\.(?:jpe?g|png|gif|webp|avif)$/i', $normalized)) {
        throw new RuntimeException('La imagen debe ser JPG, PNG, GIF, WebP o AVIF.');
    }

    $projectRoot = dirname(__DIR__);
    $galleryRoot = realpath($projectRoot . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'gallery');
    $imagePath = realpath($projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized));

    if ($galleryRoot === false || $imagePath === false) {
        throw new RuntimeException('La imagen local indicada no existe.');
    }

    $galleryPrefix = rtrim($galleryRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    if (!str_starts_with($imagePath, $galleryPrefix)) {
        throw new RuntimeException('La ruta de imagen no puede salir de images/gallery/.');
    }

    return $normalized;
}

function burnout_gallery_delete(int $id): void
{
    $statement = burnout_pdo()->prepare('DELETE FROM gallery WHERE id = :id');
    $statement->execute(['id' => $id]);

    if ($statement->rowCount() === 0) {
        throw new RuntimeException('La imagen seleccionada no existe.');
    }
}
