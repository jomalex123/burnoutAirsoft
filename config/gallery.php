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

function burnout_gallery_delete(int $id): void
{
    $statement = burnout_pdo()->prepare('DELETE FROM gallery WHERE id = :id');
    $statement->execute(['id' => $id]);

    if ($statement->rowCount() === 0) {
        throw new RuntimeException('La imagen seleccionada no existe.');
    }
}
