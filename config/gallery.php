<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

function burnout_gallery_event_tables(): void
{
    static $ready = false;

    if ($ready) {
        return;
    }

    $pdo = burnout_pdo();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS gallery_events (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          event_id INT UNSIGNED DEFAULT NULL,
          title VARCHAR(180) NOT NULL,
          slug VARCHAR(190) NOT NULL,
          is_visible TINYINT(1) NOT NULL DEFAULT 1,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY gallery_events_slug_unique (slug),
          UNIQUE KEY gallery_events_event_id_unique (event_id),
          KEY gallery_events_visible_index (is_visible),
          CONSTRAINT gallery_events_event_id_foreign
            FOREIGN KEY (event_id) REFERENCES events (id)
            ON UPDATE CASCADE
            ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS gallery_event_images (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          gallery_event_id BIGINT UNSIGNED NOT NULL,
          src TEXT NULL,
          original_name VARCHAR(255) DEFAULT NULL,
          image_mime VARCHAR(80) DEFAULT NULL,
          image_data MEDIUMBLOB NULL,
          image_size INT UNSIGNED NOT NULL DEFAULT 0,
          image_hash CHAR(64) DEFAULT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY gallery_event_images_event_index (gallery_event_id, id),
          CONSTRAINT gallery_event_images_event_id_foreign
            FOREIGN KEY (gallery_event_id) REFERENCES gallery_events (id)
            ON UPDATE CASCADE
            ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    burnout_gallery_remove_unused_event_fields_if_present($pdo);

    $ready = true;
}

function burnout_gallery_remove_unused_event_fields_if_present(PDO $pdo): void
{
    $schema = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

    if ($schema === '') {
        return;
    }

    burnout_gallery_ensure_event_image_storage($pdo, $schema);
    burnout_gallery_ensure_image_index($pdo, $schema);
}

function burnout_gallery_column_exists(PDO $pdo, string $schema, string $table, string $column): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :schema
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );
    $statement->execute([
        'schema' => $schema,
        'table_name' => $table,
        'column_name' => $column,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function burnout_gallery_column_is_nullable(PDO $pdo, string $schema, string $table, string $column): bool
{
    $statement = $pdo->prepare(
        'SELECT IS_NULLABLE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = :schema
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name
         LIMIT 1'
    );
    $statement->execute([
        'schema' => $schema,
        'table_name' => $table,
        'column_name' => $column,
    ]);

    return strtoupper((string) $statement->fetchColumn()) === 'YES';
}

function burnout_gallery_ensure_event_image_storage(PDO $pdo, string $schema): void
{
    if (
        burnout_gallery_column_exists($pdo, $schema, 'gallery_event_images', 'src')
        && !burnout_gallery_column_is_nullable($pdo, $schema, 'gallery_event_images', 'src')
    ) {
        $pdo->exec('ALTER TABLE gallery_event_images MODIFY src TEXT NULL');
    }

    burnout_gallery_add_column_if_missing($pdo, $schema, 'gallery_event_images', 'original_name', 'original_name VARCHAR(255) DEFAULT NULL');
    burnout_gallery_add_column_if_missing($pdo, $schema, 'gallery_event_images', 'image_mime', 'image_mime VARCHAR(80) DEFAULT NULL');
    burnout_gallery_add_column_if_missing($pdo, $schema, 'gallery_event_images', 'image_data', 'image_data MEDIUMBLOB NULL');
    burnout_gallery_add_column_if_missing($pdo, $schema, 'gallery_event_images', 'image_size', 'image_size INT UNSIGNED NOT NULL DEFAULT 0');
    burnout_gallery_add_column_if_missing($pdo, $schema, 'gallery_event_images', 'image_hash', 'image_hash CHAR(64) DEFAULT NULL');
}

function burnout_gallery_add_column_if_missing(PDO $pdo, string $schema, string $table, string $column, string $definition): void
{
    if (!burnout_gallery_column_exists($pdo, $schema, $table, $column)) {
        $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $definition);
    }
}

function burnout_gallery_ensure_image_index(PDO $pdo, string $schema): void
{
    $index = $pdo->prepare(
        'SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = :schema
           AND TABLE_NAME = :table_name
           AND INDEX_NAME = :index_name'
    );
    $index->execute([
        'schema' => $schema,
        'table_name' => 'gallery_event_images',
        'index_name' => 'gallery_event_images_event_index',
    ]);

    if ((int) $index->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE gallery_event_images ADD KEY gallery_event_images_event_index (gallery_event_id, id)');
    }
}

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

function burnout_gallery_events_for_select(): array
{
    burnout_gallery_event_tables();

    $statement = burnout_pdo()->query(
        'SELECT e.id, e.title, e.event_date, e.time_slot
         FROM events e
         WHERE NOT EXISTS (
            SELECT 1
            FROM gallery_events ge
            WHERE ge.event_id = e.id
         )
         ORDER BY e.event_date DESC, e.id DESC'
    );

    return array_map(static function (array $event): array {
        return [
            'id' => (int) $event['id'],
            'title' => (string) $event['title'],
            'date' => burnout_gallery_format_event_date((string) $event['event_date']),
            'turn' => burnout_gallery_time_slot_label((string) $event['time_slot']),
        ];
    }, $statement->fetchAll());
}

function burnout_gallery_admin_events(): array
{
    burnout_gallery_event_tables();

    $statement = burnout_pdo()->query(
        'SELECT
            ge.id,
            ge.event_id,
            ge.title,
            ge.slug,
            ge.is_visible,
            ge.created_at,
            e.event_date,
            e.time_slot,
            COUNT(gei.id) AS image_count
         FROM gallery_events ge
         LEFT JOIN events e ON e.id = ge.event_id
         LEFT JOIN gallery_event_images gei ON gei.gallery_event_id = ge.id
         GROUP BY ge.id, ge.event_id, ge.title, ge.slug, ge.is_visible, ge.created_at, e.event_date, e.time_slot
         ORDER BY COALESCE(e.event_date, DATE(ge.created_at)) DESC, ge.id DESC'
    );

    $events = $statement->fetchAll();
    $imagesByEvent = burnout_gallery_event_images_by_event(array_column($events, 'id'));

    return array_map(static function (array $event) use ($imagesByEvent): array {
        $eventId = (int) $event['id'];

        return [
            'id' => $eventId,
            'source_event_id' => isset($event['event_id']) ? (int) $event['event_id'] : null,
            'title' => (string) $event['title'],
            'slug' => (string) $event['slug'],
            'is_visible' => (bool) $event['is_visible'],
            'date' => $event['event_date'] ? burnout_gallery_format_event_date((string) $event['event_date']) : '',
            'turn' => $event['time_slot'] ? burnout_gallery_time_slot_label((string) $event['time_slot']) : '',
            'image_count' => (int) $event['image_count'],
            'images' => $imagesByEvent[$eventId] ?? [],
        ];
    }, $events);
}

function burnout_gallery_public_events(): array
{
    burnout_gallery_event_tables();

    $statement = burnout_pdo()->query(
        'SELECT
            ge.id,
            ge.title,
            ge.slug,
            e.event_date,
            e.time_slot,
            COUNT(gei.id) AS image_count
         FROM gallery_events ge
         LEFT JOIN events e ON e.id = ge.event_id
         INNER JOIN gallery_event_images gei ON gei.gallery_event_id = ge.id
         WHERE ge.is_visible = 1
         GROUP BY ge.id, ge.title, ge.slug, e.event_date, e.time_slot
         ORDER BY COALESCE(e.event_date, DATE(ge.created_at)) DESC, ge.id DESC'
    );

    $events = $statement->fetchAll();
    $imagesByEvent = burnout_gallery_event_images_by_event(array_column($events, 'id'));

    return array_map(static function (array $event) use ($imagesByEvent): array {
        $eventId = (int) $event['id'];
        $images = $imagesByEvent[$eventId] ?? [];
        $cover = $images[0]['src'] ?? 'images/resources/maintenance.webp';

        return [
            'id' => $eventId,
            'slug' => (string) $event['slug'],
            'title' => (string) $event['title'],
            'date' => $event['event_date'] ? burnout_gallery_format_event_date((string) $event['event_date']) : '',
            'turn' => $event['time_slot'] ? burnout_gallery_time_slot_label((string) $event['time_slot']) : '',
            'imageCount' => (int) $event['image_count'],
            'cover' => $cover,
            'images' => $images,
        ];
    }, $events);
}

function burnout_gallery_event_add(int $eventId, string $title, bool $isVisible): void
{
    burnout_gallery_event_tables();

    $event = burnout_gallery_find_source_event($eventId);

    if (!$event) {
        throw new RuntimeException('La partida seleccionada no existe.');
    }

    $existing = burnout_pdo()->prepare('SELECT id FROM gallery_events WHERE event_id = :event_id LIMIT 1');
    $existing->execute(['event_id' => $eventId]);

    if ($existing->fetch()) {
        throw new RuntimeException('Esta partida ya tiene una galeria creada.');
    }

    $title = trim($title);

    if ($title === '') {
        $title = (string) $event['title'];
    }

    $slug = burnout_gallery_unique_slug($title . ' ' . (string) $event['event_date']);
    $statement = burnout_pdo()->prepare(
        'INSERT INTO gallery_events (event_id, title, slug, is_visible)
         VALUES (:event_id, :title, :slug, :is_visible)'
    );
    $statement->execute([
        'event_id' => $eventId,
        'title' => $title,
        'slug' => $slug,
        'is_visible' => $isVisible ? 1 : 0,
    ]);
}

function burnout_gallery_event_delete(int $id): void
{
    burnout_gallery_event_tables();

    $statement = burnout_pdo()->prepare('DELETE FROM gallery_events WHERE id = :id');
    $statement->execute(['id' => $id]);

    if ($statement->rowCount() === 0) {
        throw new RuntimeException('La galeria seleccionada no existe.');
    }
}

function burnout_gallery_event_image_add(int $galleryEventId, array|string $image): void
{
    burnout_gallery_event_tables();

    if (!burnout_gallery_event_exists($galleryEventId)) {
        throw new RuntimeException('La galeria seleccionada no existe.');
    }

    if (is_string($image)) {
        $src = burnout_gallery_validate_src($image);
        $statement = burnout_pdo()->prepare(
            'INSERT INTO gallery_event_images (gallery_event_id, src)
             VALUES (:gallery_event_id, :src)'
        );
        $statement->execute([
            'gallery_event_id' => $galleryEventId,
            'src' => $src,
        ]);

        return;
    }

    $imageData = (string) ($image['data'] ?? '');
    $mime = (string) ($image['mime'] ?? '');
    $size = (int) ($image['size'] ?? 0);
    $hash = (string) ($image['hash'] ?? '');
    $originalName = burnout_gallery_clean_original_name((string) ($image['original_name'] ?? ''));

    if ($imageData === '' || $mime === '' || $size <= 0 || $hash === '') {
        throw new RuntimeException('La imagen subida no es valida.');
    }

    $statement = burnout_pdo()->prepare(
        'INSERT INTO gallery_event_images (gallery_event_id, src, original_name, image_mime, image_data, image_size, image_hash)
         VALUES (:gallery_event_id, NULL, :original_name, :image_mime, :image_data, :image_size, :image_hash)'
    );
    $statement->execute([
        'gallery_event_id' => $galleryEventId,
        'original_name' => $originalName !== '' ? $originalName : null,
        'image_mime' => $mime,
        'image_data' => $imageData,
        'image_size' => $size,
        'image_hash' => $hash,
    ]);
}

function burnout_gallery_store_upload(array $file): ?array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se ha podido subir la imagen.');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('La imagen subida no es valida.');
    }

    if ($size <= 0 || $size > 8 * 1024 * 1024) {
        throw new RuntimeException('La imagen debe pesar como maximo 8 MB.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $tmpName) : false;

    if ($finfo) {
        finfo_close($finfo);
    }

    $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
    ];

    if (!is_string($mime) || !in_array($mime, $allowedMimes, true)) {
        throw new RuntimeException('La imagen debe ser JPG, PNG, GIF, WebP o AVIF.');
    }

    $data = file_get_contents($tmpName);

    if (!is_string($data) || $data === '') {
        throw new RuntimeException('No se ha podido leer la imagen subida.');
    }

    return [
        'original_name' => (string) ($file['name'] ?? ''),
        'mime' => $mime,
        'data' => $data,
        'size' => strlen($data),
        'hash' => hash('sha256', $data),
    ];
}

function burnout_gallery_upload_files(array $files): array
{
    if (!isset($files['tmp_name'])) {
        return [];
    }

    if (!is_array($files['tmp_name'])) {
        return ((int) ($files['error'] ?? UPLOAD_ERR_NO_FILE)) === UPLOAD_ERR_NO_FILE ? [] : [$files];
    }

    $normalized = [];
    $count = count($files['tmp_name']);

    for ($index = 0; $index < $count; $index++) {
        $file = [
            'name' => (string) ($files['name'][$index] ?? ''),
            'type' => (string) ($files['type'][$index] ?? ''),
            'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
            'error' => (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($files['size'][$index] ?? 0),
        ];

        if ($file['error'] !== UPLOAD_ERR_NO_FILE) {
            $normalized[] = $file;
        }
    }

    return $normalized;
}

function burnout_gallery_event_image_delete(int $id): void
{
    burnout_gallery_event_tables();

    $statement = burnout_pdo()->prepare('DELETE FROM gallery_event_images WHERE id = :id');
    $statement->execute(['id' => $id]);

    if ($statement->rowCount() === 0) {
        throw new RuntimeException('La imagen seleccionada no existe.');
    }
}

function burnout_gallery_event_images_by_event(array $galleryEventIds): array
{
    burnout_gallery_event_tables();

    $ids = array_values(array_unique(array_map('intval', $galleryEventIds)));

    if (!$ids) {
        return [];
    }

    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $statement = burnout_pdo()->prepare(
        'SELECT
            id,
            gallery_event_id,
            src,
            original_name,
            image_size,
            image_hash,
            image_data IS NOT NULL AS has_image_data
         FROM gallery_event_images
         WHERE gallery_event_id IN (' . $placeholders . ')
         ORDER BY gallery_event_id ASC, id ASC'
    );
    $statement->execute($ids);
    $images = [];

    foreach ($statement->fetchAll() as $image) {
        $eventId = (int) $image['gallery_event_id'];
        $images[$eventId][] = burnout_gallery_event_image_item($image);
    }

    return $images;
}

function burnout_gallery_event_image_item(array $image): array
{
    $id = (int) $image['id'];
    $src = trim((string) ($image['src'] ?? ''));
    $hash = (string) ($image['image_hash'] ?? '');
    $hasImageData = (bool) ((int) ($image['has_image_data'] ?? 0));

    if ($hasImageData) {
        $src = 'gallery_event_image.php?id=' . $id;

        if ($hash !== '') {
            $src .= '&v=' . rawurlencode(substr($hash, 0, 12));
        }
    } elseif ($src === '') {
        $src = 'images/resources/maintenance.webp';
    }

    $label = burnout_gallery_image_label((string) ($image['original_name'] ?? ''), $src, $id);

    return [
        'id' => $id,
        'src' => $src,
        'alt' => $label,
        'label' => $label,
        'size' => (int) ($image['image_size'] ?? 0),
        'storedInDatabase' => $hasImageData,
    ];
}

function burnout_gallery_image_label(string $originalName, string $src, int $id): string
{
    $originalName = burnout_gallery_clean_original_name($originalName);

    if ($originalName !== '') {
        return $originalName;
    }

    $path = parse_url($src, PHP_URL_PATH);
    $basename = is_string($path) ? basename($path) : '';

    return $basename !== '' && $basename !== '.' ? $basename : 'Imagen ' . $id;
}

function burnout_gallery_clean_original_name(string $name): string
{
    $name = trim(str_replace('\\', '/', $name));
    $name = basename($name);
    $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';

    return substr($name, 0, 255);
}

function burnout_gallery_find_source_event(int $eventId): ?array
{
    burnout_gallery_event_tables();

    $statement = burnout_pdo()->prepare(
        'SELECT id, title, event_date, time_slot
         FROM events
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $eventId]);
    $event = $statement->fetch();

    return $event ?: null;
}

function burnout_gallery_event_exists(int $galleryEventId): bool
{
    burnout_gallery_event_tables();

    $statement = burnout_pdo()->prepare('SELECT id FROM gallery_events WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $galleryEventId]);

    return (bool) $statement->fetch();
}

function burnout_gallery_unique_slug(string $value): string
{
    burnout_gallery_event_tables();

    $baseSlug = burnout_gallery_slugify($value);
    $slug = $baseSlug;
    $counter = 2;
    $statement = burnout_pdo()->prepare('SELECT id FROM gallery_events WHERE slug = :slug LIMIT 1');

    while (true) {
        $statement->execute(['slug' => $slug]);

        if (!$statement->fetch()) {
            return $slug;
        }

        $slug = $baseSlug . '-' . $counter;
        $counter++;
    }
}

function burnout_gallery_slugify(string $value): string
{
    $value = trim($value);
    $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

    if ($converted !== false) {
        $value = $converted;
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? substr($value, 0, 180) : 'galeria-evento';
}

function burnout_gallery_format_event_date(string $value): string
{
    $date = DateTime::createFromFormat('Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        return $value;
    }

    return $date->format('d/m/Y');
}

function burnout_gallery_time_slot_label(string $timeSlot): string
{
    return [
        'M' => 'Manana',
        'T' => 'Tarde',
        'N' => 'Noche',
    ][$timeSlot] ?? $timeSlot;
}
