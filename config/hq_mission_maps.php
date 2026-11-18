<?php
declare(strict_types=1);

require_once __DIR__ . '/hq.php';

function burnout_hq_team_mission_map(int $operationId, string $team, bool $includeImage = false): ?array
{
    $fields = $includeImage ? ', mm.annotations' : '';
    $query = burnout_pdo()->prepare("SELECT m.id, SHA2(mm.annotations, 256) AS version" . $fields . " FROM hq_missions m JOIN hq_operations o ON o.id = m.operation_id LEFT JOIN hq_mission_maps mm ON mm.catalog_id = m.catalog_id WHERE m.operation_id = ? AND m.team = ? AND m.status = 'active' AND o.status = 'active' ORDER BY m.id LIMIT 1");
    $query->execute([$operationId, $team]);
    $row = $query->fetch();
    if (!$row) return null;
    $map = ['mission_id' => (int) $row['id'], 'version' => $row['version'] ?? ''];
    if ($includeImage) $map['annotations'] = $row['annotations'] === null ? null : 'data:image/png;base64,' . base64_encode($row['annotations']);
    return $map;
}

function burnout_hq_mission_map_png($image): string
{
    if (!is_string($image) || strlen($image) > 4000000 || !str_starts_with($image, 'data:image/png;base64,')) {
        throw new BurnoutUserException('El mapa no es válido o supera el límite permitido.');
    }
    $binary = base64_decode(substr($image, 22), true);
    $size = $binary === false ? false : @getimagesizefromstring($binary);
    if (!$size || $size[2] !== IMAGETYPE_PNG || $size[0] !== 1600 || $size[1] !== 1131) {
        throw new BurnoutUserException('Las anotaciones deben ser un PNG de 1600 × 1131 píxeles.');
    }
    return $binary;
}

// El llamador mantiene bloqueada la fila del catálogo dentro de su transacción.
function burnout_hq_mission_map_store(int $catalogId, string $binary): void
{
    $query = burnout_pdo()->prepare('INSERT INTO hq_mission_maps (catalog_id, annotations) VALUES (?, ?) ON DUPLICATE KEY UPDATE annotations = VALUES(annotations)');
    $query->execute([$catalogId, $binary]);
}
