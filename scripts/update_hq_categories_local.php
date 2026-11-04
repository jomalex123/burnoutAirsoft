<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/hq.php';

// Explicit local-only installer. No remote configuration or web execution.
$config = burnout_database_config();
$environment = getenv('BURNOUT_ENV') ?: (burnout_env_config()['default'] ?? 'local');
if ($environment !== 'local' || !in_array($config['host'], ['127.0.0.1', 'localhost'], true) || (int) $config['port'] !== 3306) {
    exit("Solo se permite la base local de XAMPP.\n");
}
$pdo = burnout_pdo();
try {
    $read = static function (string $file): array {
        $text = str_replace("\r\n", "\n", file_get_contents($file));
        preg_match_all('/^## (.+)\n([\s\S]*?)(?=^## |\z)/m', $text, $blocks, PREG_SET_ORDER);
        $items = [];
        foreach ($blocks as $block) {
            if (!preg_match('/\*\*Descripción:\*\* ([\s\S]*?)(?=\n---|\n# |\z)/u', $block[2], $description)) {
                throw new RuntimeException('Falta una descripcion.');
            }
            preg_match('/\*\*Categoría:\*\* (.+)/u', $block[2], $category);
            $title = trim($block[1]);
            if (isset($items[$title])) throw new RuntimeException('Titulo duplicado.');
            $items[$title] = ['briefing' => trim($description[1]), 'category' => trim($category[1] ?? '')];
        }
        return $items;
    };
    $original = $read(__DIR__ . '/../misiones.md');
    $classified = $read(__DIR__ . '/../misiones_por_categorias.md');
    if (count($original) !== 45 || count($classified) !== 45) throw new RuntimeException('Se esperaban 45 misiones en ambos documentos.');
    $catalog = $pdo->query('SELECT * FROM hq_mission_catalog ORDER BY id')->fetchAll();
    foreach ($original as $title => $item) {
        if (!isset($classified[$title]) || $classified[$title]['briefing'] !== $item['briefing'] || $classified[$title]['category'] === '') {
            throw new RuntimeException('Los documentos no coinciden: ' . $title);
        }
        $hash = hash('sha256', $title . "\n" . $item['briefing']);
        $matches = array_filter($catalog, static fn (array $row): bool => $row['content_hash'] === $hash && $row['title'] === $title && $row['briefing'] === $item['briefing']);
        if (count($matches) !== 1) throw new RuntimeException('Mision local modificada o ausente: ' . $title);
    }
    echo "Documentos y catalogo local: 45 coincidencias exactas; 17 categorias.\n";
    if (!in_array('--apply', $argv, true)) { echo "Sin cambios. Usar --apply para instalar en local.\n"; exit; }
    $exists = (bool) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hq_mission_categories'")->fetchColumn();
    $backup = tempnam(sys_get_temp_dir(), 'burnout-hq-categories-');
    $json = json_encode(['catalog' => $catalog, 'categories' => $exists ? $pdo->query('SELECT * FROM hq_mission_categories')->fetchAll() : null], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    if ($backup === false || file_put_contents($backup, $json) !== strlen($json) || file_get_contents($backup) !== $json) throw new RuntimeException('No se pudo verificar la copia previa.');
    echo 'Copia previa: ' . $backup . "\n";
    $sql = file_get_contents(__DIR__ . '/../database/migrations/2026_11_04_hq_mission_categories.sql');
    // MySQL DDL commits implicitly. Create the table first; keep all data updates atomic.
    $boundary = strpos($sql, '-- DATA');
    if ($boundary === false) throw new RuntimeException('Migracion sin separador DDL/datos.');
    $pdo->exec(substr($sql, 0, $boundary));
    $pdo->beginTransaction();
    $pdo->exec(substr($sql, $boundary));
    $query = $pdo->prepare('SELECT category FROM hq_mission_catalog WHERE content_hash = ?');
    foreach ($classified as $title => $item) {
        $query->execute([hash('sha256', $title . "\n" . $item['briefing'])]);
        if ($query->fetchColumn() !== $item['category']) throw new RuntimeException('Categoria no verificada.');
    }
    $pdo->commit();
    echo "Categorias de las 45 misiones verificadas. Titulos, guiones e historial conservados.\n";
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
