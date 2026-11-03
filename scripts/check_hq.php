<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ob_start();
require_once __DIR__ . '/../config/hq.php';

// Solo XAMPP local. Crea datos ficticios y los elimina al terminar.
$config = burnout_database_config();
$environment = getenv('BURNOUT_ENV') ?: (burnout_env_config()['default'] ?? 'local');
if ($environment !== 'local' || !in_array($config['host'], ['127.0.0.1', 'localhost'], true)) {
    exit("Esta comprobación solo se permite en la base local.\n");
}
$pdo = burnout_pdo();
if (in_array('--install-local', $argv, true)) {
    $pdo->exec(file_get_contents(__DIR__ . '/../database/migrations/2026_10_30_add_hq.sql'));
    $column = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hq_operations' AND COLUMN_NAME = 'event_id'")->fetchColumn();
    if (!$column) {
        $pdo->exec(file_get_contents(__DIR__ . '/../database/migrations/2026_10_30_link_hq_events.sql'));
    }
    $column = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hq_missions' AND COLUMN_NAME = 'catalog_id'")->fetchColumn();
    if (!$column) {
        $backup = tempnam(sys_get_temp_dir(), 'burnout-hq-before-catalog-');
        if ($backup === false) throw new RuntimeException('No se ha podido preparar la copia local de misiones.');
        $rows = $pdo->query('SELECT * FROM hq_missions')->fetchAll();
        $sql = $pdo->query('SHOW CREATE TABLE hq_missions')->fetch(PDO::FETCH_NUM)[1] . ";\n";
        foreach ($rows as $row) {
            $sql .= 'INSERT INTO hq_missions (`' . implode('`, `', array_keys($row)) . '`) VALUES (' . implode(', ', array_map(static fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), array_values($row))) . ");\n";
        }
        if (file_put_contents($backup, $sql) === false) throw new RuntimeException('No se ha podido guardar la copia local de misiones.');
        echo 'Copia local de hq_missions: ' . $backup . PHP_EOL;
        $pdo->exec(file_get_contents(__DIR__ . '/../database/migrations/2026_11_02_add_hq_mission_catalog.sql'));
    }
    $statusType = $pdo->query("SHOW COLUMNS FROM hq_missions LIKE 'status'")->fetch()['Type'];
    if (strpos($statusType, "'deleted'") === false) {
        $backup = tempnam(sys_get_temp_dir(), 'burnout-hq-before-deleted-');
        if ($backup === false) throw new RuntimeException('No se ha podido preparar la copia local de misiones.');
        $rows = $pdo->query('SELECT * FROM hq_missions')->fetchAll();
        $sql = $pdo->query('SHOW CREATE TABLE hq_missions')->fetch(PDO::FETCH_NUM)[1] . ";\n";
        foreach ($rows as $row) {
            $sql .= 'INSERT INTO hq_missions (`' . implode('`, `', array_keys($row)) . '`) VALUES (' . implode(', ', array_map(static fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), array_values($row))) . ");\n";
        }
        if (file_put_contents($backup, $sql) === false) throw new RuntimeException('No se ha podido guardar la copia local de misiones.');
        echo 'Copia local de hq_missions: ' . $backup . PHP_EOL;
        $pdo->exec(file_get_contents(__DIR__ . '/../database/migrations/2026_11_03_hq_deleted_missions.sql'));
    }
    $pdo->exec(file_get_contents(__DIR__ . '/../database/migrations/2026_11_03_add_hq_maps.sql'));
    $column = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hq_missions' AND COLUMN_NAME = 'sort_order'")->fetchColumn();
    if (!$column) $pdo->exec(file_get_contents(__DIR__ . '/../database/migrations/2026_11_03_hq_mission_order.sql'));
    $column = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'hq_mission_catalog' AND COLUMN_NAME = 'category'")->fetchColumn();
    if (!$column) $pdo->exec(file_get_contents(__DIR__ . '/../database/migrations/2026_11_03_hq_mission_classification.sql'));
    echo "Migración HQ aplicada en local.\n";
}
$operations = [];
$events = [];
$sessions = [];
$adminId = 0;
$passed = 0;
$catalogCreated = [];
$catalogExisting = array_map('intval', $pdo->query('SELECT id FROM hq_mission_catalog')->fetchAll(PDO::FETCH_COLUMN));
function hq_check(bool $condition, string $message): void
{
    global $passed;
    if (!$condition) { throw new RuntimeException($message); }
    $passed++;
    echo 'OK · ' . $message . PHP_EOL;
}
function hq_event(string $date, string $slot = 'M'): int
{
    global $pdo, $events;
    $pdo->prepare('INSERT INTO events (title, event_date, time_slot, max_attendees) VALUES (?, ?, ?, ?)')->execute(['HQ TEST', $date, $slot, 40]);
    $id = (int) $pdo->lastInsertId();
    $events[] = $id;
    return $id;
}
function hq_session(?int $admin = null): array
{
    global $sessions;
    session_id(bin2hex(random_bytes(16)));
    burnout_start_session();
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException('No se pueden crear sesiones de XAMPP. Ejecuta esta prueba con acceso a su directorio temporal.');
    }
    $_SESSION = ['csrf_token' => bin2hex(random_bytes(32))];
    if ($admin) { $_SESSION['admin_user_id'] = $admin; }
    $result = ['id' => session_id(), 'csrf' => $_SESSION['csrf_token']];
    $sessions[] = $result['id'];
    session_write_close();
    return $result;
}
function hq_request(array &$session, string $action, array $data = [], bool $post = false, bool $team = false): array
{
    global $catalogCreated, $catalogExisting;
    $data += ['action' => $action, 'view' => $team ? 'team' : 'admin'];
    if ($post) { $data += ['csrf_token' => $session['csrf']]; }
    $url = 'http://localhost/burnoutAirsoft/hq_api.php';
    $curl = curl_init($url . ($post ? '' : '?' . http_build_query($data)));
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_COOKIE => session_name() . '=' . $session['id'],
        CURLOPT_HEADERFUNCTION => function ($handle, string $header) use (&$session): int {
            if (preg_match('/^Set-Cookie: ' . preg_quote(session_name(), '/') . '=([^;]+)/i', $header, $matches)) {
                $session['id'] = $matches[1];
            }
            return strlen($header);
        }]);
    if ($post) { curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data)); }
    $body = curl_exec($curl);
    if ($body === false) { throw new RuntimeException(curl_error($curl)); }
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $result = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (!empty($result['catalog_id']) && !in_array((int) $result['catalog_id'], $catalogExisting, true)) $catalogCreated[] = (int) $result['catalog_id'];
    return [$status, $result];
}
function hq_page(array $session, string $page): DOMXPath
{
    $curl = curl_init('http://localhost/burnoutAirsoft/' . $page);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_COOKIE => session_name() . '=' . $session['id']]);
    $html = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($status !== 200 || !is_string($html)) { throw new RuntimeException('No se ha podido cargar ' . $page); }
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return new DOMXPath($document);
}
try {
    $rateIdentifier = 'audit-' . bin2hex(random_bytes(10));
    hq_check(burnout_rate_limit_hit('hq_audit_test', $rateIdentifier, 3, 60, 60) === 0, 'Primer intento permitido');
    hq_check(burnout_rate_limit_hit('hq_audit_test', $rateIdentifier, 3, 60, 60) === 0, 'Segundo intento permitido');
    hq_check(burnout_rate_limit_hit('hq_audit_test', $rateIdentifier, 3, 60, 60) > 0, 'Limita intentos de forma transaccional');
    burnout_rate_limit_clear('hq_audit_test', $rateIdentifier);
    $query = $pdo->prepare('INSERT INTO admin_users (username, password_hash, display_name) VALUES (?, ?, ?)');
    $query->execute(['hq_test_' . bin2hex(random_bytes(6)), password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'Prueba HQ']);
    $adminId = (int) $pdo->lastInsertId();
    $admin = hq_session($adminId);
    $alpha = hq_session();
    $bravo = hq_session();
    $page = hq_page($admin, 'admin_hq.php');
    hq_check($page->query('//main')->length === 1 && $page->query('//*[@data-map-panel]')->length === 4, 'Carga directa de HQ con un único main y cuatro pestañas');
    hq_check($page->query('//script[@src="assets/js/hq.js"]')->length === 1 && $page->query('//link[@href="assets/css/hq.css"]')->length === 1 && $page->query('//*[@data-hq-console]')->length === 1, 'El HTML incluye el contenedor y los recursos para navegación parcial');
    $seenIds = [];
    foreach ($page->query('//*[@id]') as $element) {
        $elementId = $element->getAttribute('id');
        if (isset($seenIds[$elementId])) throw new RuntimeException('Identificador HTML duplicado en HQ.');
        $seenIds[$elementId] = true;
    }
    hq_check(true, 'HQ no tiene identificadores HTML duplicados');
    foreach ($page->query('//*[@role="dialog"]') as $dialog) {
        $labelId = $dialog->getAttribute('aria-labelledby');
        if ($labelId === '' || !isset($seenIds[$labelId])) throw new RuntimeException('Dialogo sin titulo accesible en HQ.');
    }
    hq_check(true, 'Los dialogos HQ referencian titulos existentes');
    hq_check($page->query('//*[@data-hq-catalog-editor-popup]//form[@data-hq-form="catalog_save"]')->length === 1 && $page->query('//*[@data-hq-catalog]//form')->length === 0, 'Editor unico dentro de su popup');
    hq_check($page->query('//*[@data-hq-catalog-browser="import"]//*[@data-hq-table-size]')->length === 0 && $page->query('//*[@data-hq-catalog-browser="manage"]//*[@data-hq-table-size]')->length === 1, 'Importar mantiene cinco filas fijas y Gestionar permite elegir');
    $page = hq_page($alpha, 'hq_equipo.php');
    hq_check($page->query('//main')->length === 1 && $page->query('//*[@data-hq-form="login"]')->length === 1 && $page->query('//*[@data-map-panel="ajustes"]')->length === 0, 'Carga de enlace sin herramientas administrativas');
    [$status] = hq_request($alpha, 'list');
    hq_check($status === 401, 'Sin sesión administrativa no se enumeran partidas');
    $today = new DateTimeImmutable(burnout_hq_today(), new DateTimeZone('Europe/Madrid'));
    $pastEvent = hq_event($today->modify('-1 day')->format('Y-m-d'));
    $todayEvent = hq_event($today->format('Y-m-d'));
    $afternoonEvent = hq_event($today->format('Y-m-d'), 'T');
    $futureEvent = hq_event($today->modify('+1 day')->format('Y-m-d'));
    [$status, $listed] = hq_request($admin, 'list');
    $available = array_map('intval', array_column($listed['operations'], 'event_id'));
    hq_check(!in_array($pastEvent, $available, true) && in_array($todayEvent, $available, true) && in_array($futureEvent, $available, true), 'El selector común incluye hoy y futuro, excluyendo eventos pasados');
    $sameDay = array_values(array_filter($listed['operations'], static fn(array $event): bool => in_array((int) $event['event_id'], [$todayEvent, $afternoonEvent], true)));
    hq_check(count($sameDay) === 2 && $sameDay[0]['time_slot'] === 'M' && $sameDay[1]['time_slot'] === 'T', 'Los turnos del mismo día permanecen separados y ordenados');
    [$status] = hq_request($admin, 'select', ['event_id' => $pastEvent], true);
    hq_check($status === 422, 'El servidor rechaza seleccionar un evento pasado');
    [$status] = hq_request($admin, 'select', ['event_id' => 0], true);
    hq_check($status === 422, 'El servidor rechaza eventos inexistentes');
    [$status] = hq_request($admin, 'create', ['title' => 'Partida independiente'], true);
    hq_check($status === 422, 'El HQ ya no permite crear partidas independientes');
    [$status, $data] = hq_request($admin, 'select', ['event_id' => $todayEvent], true);
    $id = (int) ($data['operation_id'] ?? 0);
    if ($id) { $operations[] = $id; }
    hq_check($status === 200 && $id > 0, 'Selección de evento con operación HQ vinculada');
    [$status, $again] = hq_request($admin, 'select', ['event_id' => $todayEvent], true);
    hq_check((int) $again['operation_id'] === $id, 'Una única operación por evento al volver a seleccionarlo');
    $pdo->prepare('UPDATE events SET title = ? WHERE id = ?')->execute(['HQ TEST actualizado', $todayEvent]);
    [$status, $state] = hq_request($admin, 'state', ['operation_id' => $id, 'channel' => 'alpha']);
    hq_check($state['operation']['title'] === 'HQ TEST actualizado' && (int) $state['operation']['event_id'] === $todayEvent, 'Nombre y fecha se consultan desde el calendario');
    [$status] = hq_request($admin, 'settings', ['operation_id' => $id, 'csrf_token' => 'incorrecto'], true);
    hq_check($status === 403, 'Protección CSRF en acciones POST');
    foreach (['1 OR 1=1', ['1'], '-1', '1.5'] as $invalidId) {
        [$status] = hq_request($admin, 'state', ['operation_id' => $invalidId, 'channel' => 'alpha']);
        hq_check($status === 422, 'Rechaza identificadores no enteros antes de consultar SQL');
    }
    [$status] = hq_request($admin, 'state', ['operation_id' => $id, 'channel' => 'alpha', 'after' => ['1']]);
    hq_check($status === 422, 'Rechaza cursores mal formados');
    foreach (['alpha', 'bravo'] as $team) {
        [$status, $access] = hq_request($admin, 'access', ['operation_id' => $id, 'team' => $team], true);
        $session = &$alpha;
        if ($team === 'bravo') { unset($session); $session = &$bravo; }
        [$status] = hq_request($session, 'login', ['operation_id' => $id, 'team' => $team, 'code' => $access['code']], true, true);
        hq_check($status === 200, 'Acceso de enlace ' . $team);
        [$status, $codeLogin] = hq_request($session, 'login', ['code' => $access['code']], true, true);
        hq_check($status === 200 && (int) $codeLogin['operation_id'] === $id && $codeLogin['team'] === $team, 'Acceso solo con codigo identifica partida y equipo');
        [$status, $restored] = hq_request($session, 'state', ['channel' => $team], false, true);
        hq_check($status === 200 && (int) $restored['operation']['id'] === $id, 'Recupera la partida desde la sesion sin identificador');
        unset($session);
        foreach (['draft', 'active'] as $missionStatus) {
            [$status] = hq_request($admin, 'mission', ['operation_id' => $id, 'team' => $team, 'title' => $team . ' ' . $missionStatus, 'briefing' => '<script>prueba</script>', 'status' => $missionStatus], true);
            hq_check($status === 200, 'Misión ' . $team . ' ' . $missionStatus);
            if ($missionStatus === 'active') {
                $pendingId = $pdo->query('SELECT MAX(id) FROM hq_missions')->fetchColumn();
                hq_check($pdo->query('SELECT status FROM hq_missions WHERE id = ' . (int) $pendingId)->fetchColumn() === 'draft', 'Crear siempre deja la mision pendiente');
                [$status] = hq_request($admin, 'mission_status', ['operation_id' => $id, 'mission_id' => $pendingId, 'status' => 'active'], true);
                hq_check($status === 200, 'HQ inicia la mision pendiente');
                $otherId = $pdo->query('SELECT MAX(id) FROM hq_missions WHERE id < ' . (int) $pendingId)->fetchColumn();
                [$status] = hq_request($admin, 'mission_status', ['operation_id' => $id, 'mission_id' => $otherId, 'status' => 'active'], true);
                hq_check($status === 422, 'No permite dos misiones en curso por equipo');
            }
        }
    }
    $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';
    [$status] = hq_request($admin, 'map_save', ['operation_id' => $id, 'annotations' => $png], true);
    hq_check($status === 200, 'Guarda anotaciones del mapa');
    [$status, $mapData] = hq_request($admin, 'map', ['operation_id' => $id]);
    hq_check($status === 200 && $mapData['annotations'] === $png, 'Recupera las anotaciones persistidas');
    [$status] = hq_request($alpha, 'map', ['operation_id' => $id], false, true);
    hq_check($status === 403, 'El enlace no accede al mapa administrativo');
    [$status] = hq_request($admin, 'map_save', ['operation_id' => $id, 'annotations' => 'imagen invalida'], true);
    hq_check($status === 422, 'Rechaza anotaciones invalidas');
    [$status] = hq_request($admin, 'map_save', ['operation_id' => $id, 'annotations' => $png, 'csrf_token' => 'incorrecto'], true);
    hq_check($status === 403, 'CSRF protege el mapa');
    [$status] = hq_request($alpha, 'map_save', ['operation_id' => $id, 'annotations' => $png], true, true);
    hq_check($status === 403, 'El enlace no puede escribir el mapa');
    [$status] = hq_request($admin, 'map_save', ['operation_id' => $id, 'annotations' => ['invalido']], true);
    hq_check($status === 422, 'Rechaza mapas con tipo incorrecto');
    $oversize = base64_decode(substr($png, 22));
    $oversize = substr_replace($oversize, pack('N', 2001), 16, 4);
    [$status] = hq_request($admin, 'map_save', ['operation_id' => $id, 'annotations' => 'data:image/png;base64,' . base64_encode($oversize)], true);
    hq_check($status === 422, 'Rechaza dimensiones de imagen superiores al limite');
    [$status, $state] = hq_request($alpha, 'state', ['operation_id' => $id, 'channel' => 'bravo'], false, true);
    hq_check($status === 200 && $state['channel'] === 'alpha' && count($state['missions']) === 1 && $state['missions'][0]['team'] === 'alpha', 'Alpha no ve Bravo ni borradores aunque manipule el canal');
    $missionId = $state['missions'][0]['id'];
    hq_check(!array_key_exists('catalog_id', $state['missions'][0]) && !array_key_exists('is_imported', $state['missions'][0]), 'El enlace no recibe metadatos internos del catalogo');
    [$status] = hq_request($alpha, 'state', ['operation_id' => $id + 100000, 'channel' => 'alpha'], false, true);
    hq_check($status === 401, 'El enlace no accede a otras partidas');
    [$status] = hq_request($alpha, 'mission', ['operation_id' => $id], true, true);
    hq_check($status === 403, 'El enlace no crea misiones');
    [$status] = hq_request($bravo, 'ack', ['operation_id' => $id, 'mission_id' => $missionId], true, true);
    [$status, $state] = hq_request($alpha, 'state', ['operation_id' => $id, 'channel' => 'alpha'], false, true);
    hq_check($state['missions'][0]['acknowledged_at'] === null, 'Bravo no confirma misiones de Alpha');
    hq_request($alpha, 'ack', ['operation_id' => $id, 'mission_id' => $missionId], true, true);
    [$status, $state] = hq_request($alpha, 'state', ['operation_id' => $id, 'channel' => 'alpha'], false, true);
    hq_check($state['missions'][0]['acknowledged_at'] !== null, 'Confirmación de recepción persistente');
    [$status] = hq_request($alpha, 'mission_update', ['operation_id' => $id, 'mission_id' => $missionId, 'title' => 'No autorizado', 'briefing' => 'No autorizado'], true, true);
    hq_check($status === 403, 'El enlace no edita guiones');
    [$status] = hq_request($admin, 'mission_update', ['operation_id' => $id, 'mission_id' => $missionId, 'title' => 'Guion actualizado', 'briefing' => 'Objetivos actualizados'], true);
    hq_check($status === 200, 'HQ puede editar un guion existente');
    [$status, $state] = hq_request($alpha, 'state', ['operation_id' => $id, 'channel' => 'alpha'], false, true);
    hq_check(count($state['missions']) === 1 && (int) $state['missions'][0]['id'] === (int) $missionId && $state['missions'][0]['briefing'] === 'Objetivos actualizados' && $state['missions'][0]['status'] === 'active' && $state['missions'][0]['acknowledged_at'] === null, 'Editar conserva misión y estado y solicita confirmar la nueva versión');
    [$status] = hq_request($admin, 'mission_update', ['operation_id' => $id, 'mission_id' => $missionId + 100000, 'title' => 'No existe', 'briefing' => 'Prueba'], true);
    hq_check($status === 404, 'Editar rechaza misiones ajenas o inexistentes');
    hq_request($alpha, 'ack', ['operation_id' => $id, 'mission_id' => $missionId], true, true);
    [$status, $catalogState] = hq_request($admin, 'catalog');
    hq_check($status === 200 && count($catalogState['missions']) >= 45, 'Catálogo inicial con las 45 misiones y sus variantes');
    [$status] = hq_request($alpha, 'catalog', ['operation_id' => $id], false, true);
    hq_check($status === 403, 'El enlace no puede consultar el catálogo de HQ');
    $testTitle = 'HQ CATALOGO TEST ' . bin2hex(random_bytes(5));
    [$status, $createdCatalog] = hq_request($admin, 'catalog_save', ['title' => $testTitle, 'briefing' => 'Guion de catálogo de prueba'], true);
    $catalogId = (int) $createdCatalog['catalog_id'];
    hq_check($status === 200 && $catalogId > 0, 'Ajustes crea una misión de catálogo sin depender de un evento');
    [$status] = hq_request($admin, 'catalog_save', ['catalog_id' => $catalogId, 'title' => $testTitle, 'briefing' => 'Guion de catálogo editado', 'category' => 'Inteligencia', 'mission_type' => 'Reconocimiento'], true);
    hq_check($status === 200, 'Ajustes edita un guion del catálogo');
    [$status, $classified] = hq_request($admin, 'catalog');
    $classifiedItem = array_values(array_filter($classified['missions'], static fn ($mission) => (int) $mission['id'] === $catalogId))[0];
    hq_check($status === 200 && $classifiedItem['category'] === 'Inteligencia', 'El catalogo devuelve la categoria persistida');
    [$status] = hq_request($admin, 'catalog_save', ['catalog_id' => $catalogId, 'title' => $testTitle, 'briefing' => 'Guion de catalogo', 'category' => str_repeat('x', 81)], true);
    hq_check($status === 422, 'Valida el limite de categoria');
    [$status] = hq_request($admin, 'catalog_save', ['catalog_id' => $catalogId, 'title' => $testTitle, 'briefing' => 'Guion de catalogo', 'category' => ['no valido']], true);
    hq_check($status === 422, 'Rechaza categorias invalidas');
    [$status] = hq_request($admin, 'catalog_save', ['catalog_id' => $catalogId, 'title' => $testTitle, 'briefing' => ' ', 'csrf_token' => 'incorrecto'], true);
    hq_check($status === 403, 'CSRF protege la gestión del catálogo');
    [$status] = hq_request($admin, 'mission', ['operation_id' => $id, 'team' => 'alpha', 'status' => 'draft', 'catalog_id' => $catalogId, 'title' => 'Manipulado', 'briefing' => 'Manipulado'], true);
    hq_check($status === 200, 'Importación de una misión desde el catálogo');
    [$status, $adminState] = hq_request($admin, 'state', ['operation_id' => $id, 'channel' => 'alpha']);
    $importedMission = array_values(array_filter($adminState['missions'], static fn ($mission) => $mission['title'] === $testTitle))[0];
    hq_check($importedMission['title'] === $testTitle && $importedMission['briefing'] === 'Guion de catálogo editado' && (int) $importedMission['is_imported'] === 1, 'La importación usa el texto del servidor e ignora campos manipulados');
    [$status] = hq_request($admin, 'mission_update', ['operation_id' => $id, 'mission_id' => $importedMission['id'], 'title' => 'Manipulado', 'briefing' => 'Manipulado'], true);
    hq_check($status === 422, 'Una misión importada no se convierte en manual ni permite cambiar su texto');
    [$status] = hq_request($admin, 'catalog_remove', ['catalog_id' => $catalogId], true);
    hq_check($status === 200, 'Ajustes retira una misión del catálogo');
    $inactive = $pdo->prepare('SELECT is_available FROM hq_mission_catalog WHERE id = ?');
    $inactive->execute([$catalogId]);
    hq_check((int) $inactive->fetchColumn() === 0, 'Borrar desactiva la entrada sin eliminarla de la base');
    [$status, $adminState] = hq_request($admin, 'state', ['operation_id' => $id, 'channel' => 'alpha']);
    $assigned = array_values(array_filter($adminState['missions'], static fn ($mission) => (int) $mission['id'] === (int) $importedMission['id']))[0];
    hq_check($assigned['briefing'] === 'Guion de catálogo editado', 'Retirar del catálogo conserva el guion asignado');
    [$status] = hq_request($admin, 'mission', ['operation_id' => $id, 'team' => 'alpha', 'status' => 'draft', 'catalog_id' => $catalogId], true);
    hq_check($status === 404, 'No se importan misiones retiradas');
    $replacement = $catalogState['missions'][0];
    [$status] = hq_request($admin, 'mission_update', ['operation_id' => $id, 'mission_id' => $importedMission['id'], 'catalog_id' => $replacement['id']], true);
    hq_check($status === 200, 'Un guion importado se puede sustituir por otra misión del catálogo');
    [$status, $adminState] = hq_request($admin, 'state', ['operation_id' => $id, 'channel' => 'alpha']);
    $assigned = array_values(array_filter($adminState['missions'], static fn ($mission) => (int) $mission['id'] === (int) $importedMission['id']))[0];
    hq_check($assigned['title'] === $replacement['title'] && $assigned['status'] === 'draft', 'Sustituir conserva el estado y actualiza el título y la descripción');
    hq_check(count(array_filter($catalogState['missions'], static fn ($item) => $item['briefing'] === 'Objetivos actualizados')) > 0, 'Las misiones manuales y sus guiones editados se añaden al catálogo');
    hq_request($alpha, 'message', ['operation_id' => $id, 'team' => 'bravo', 'body' => 'Recibido HQ'], true, true);
    hq_request($admin, 'message', ['operation_id' => $id, 'team' => 'alpha', 'body' => 'Adelante Alpha'], true);
    [$status, $state] = hq_request($alpha, 'state', ['operation_id' => $id, 'channel' => 'bravo'], false, true);
    hq_check(count($state['messages']) === 2 && $state['messages'][0]['sender'] === 'alpha' && $state['messages'][1]['sender'] === 'hq', 'Chat bidireccional con remitente controlado en servidor');
    [$status, $state] = hq_request($bravo, 'state', ['operation_id' => $id, 'channel' => 'alpha'], false, true);
    hq_check(count($state['messages']) === 0, 'Aislamiento de los chats');
    [$status, $state] = hq_request($admin, 'state', ['operation_id' => $id, 'channel' => 'alpha'], false);
    $last = end($state['messages'])['id'];
    [$status, $state] = hq_request($admin, 'state', ['operation_id' => $id, 'channel' => 'alpha', 'after' => $last]);
    hq_check(count($state['messages']) === 0, 'Consulta incremental sin mensajes duplicados');
    [$status] = hq_request($admin, 'message', ['operation_id' => $id, 'team' => 'alpha', 'body' => str_repeat('á', 1501)], true);
    hq_check($status === 422, 'Límite de mensajes con texto UTF-8');
    hq_request($admin, 'mission_status', ['operation_id' => $id, 'mission_id' => $missionId, 'status' => 'completed'], true);
    [$status, $state] = hq_request($alpha, 'state', ['operation_id' => $id, 'channel' => 'alpha'], false, true);
    hq_check($state['missions'][0]['status'] === 'completed' && $state['missions'][0]['acknowledged_at'] !== null, 'HQ cierra una misión conservando la confirmación de recepción');
    $pdo->prepare("UPDATE hq_missions SET status = 'active' WHERE id = ?")->execute([$missionId]);
    hq_request($admin, 'mission_status', ['operation_id' => $id, 'mission_id' => $missionId, 'status' => 'delete'], true);
    $deleted = $pdo->prepare('SELECT status, title, briefing FROM hq_missions WHERE id = ?');
    $deleted->execute([$missionId]);
    $deletedMission = $deleted->fetch();
    hq_check($deletedMission && $deletedMission['status'] === 'deleted' && $deletedMission['title'] !== '' && $deletedMission['briefing'] !== '', 'Eliminar conserva el titulo y guion como mision eliminada');
    [$status] = hq_request($alpha, 'mission_status', ['operation_id' => $id, 'mission_id' => $missionId, 'status' => 'reset'], true, true);
    hq_check($status === 403, 'El enlace no puede reiniciar misiones');
    foreach (['active', 'completed', 'failed', 'deleted'] as $resetFrom) {
        $pdo->prepare('UPDATE hq_missions SET status = ?, acknowledged_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$resetFrom, $missionId]);
        [$status] = hq_request($admin, 'mission_status', ['operation_id' => $id, 'mission_id' => $missionId, 'status' => 'reset'], true);
        $query = $pdo->prepare('SELECT status, acknowledged_at FROM hq_missions WHERE id = ?');
        $query->execute([$missionId]);
        $resetMission = $query->fetch();
        hq_check($status === 200 && $resetMission['status'] === 'draft' && $resetMission['acknowledged_at'] === null, 'Reiniciar desde ' . $resetFrom . ' vuelve a pendiente sin recepcion');
    }
    [$status, $ordered] = hq_request($admin, 'state', ['operation_id' => $id, 'channel' => 'alpha']);
    $visible = array_values(array_filter($ordered['missions'], static fn ($mission) => $mission['status'] !== 'deleted'));
    $moveId = (int) $visible[count($visible) - 1]['id'];
    $beforeId = (int) $visible[count($visible) - 2]['id'];
    [$status] = hq_request($admin, 'mission_move', ['operation_id' => $id, 'mission_id' => $moveId, 'direction' => 'up'], true);
    [$unused, $ordered] = hq_request($admin, 'state', ['operation_id' => $id, 'channel' => 'alpha']);
    $ids = array_map('intval', array_column($ordered['missions'], 'id'));
    hq_check($status === 200 && array_search($moveId, $ids, true) < array_search($beforeId, $ids, true), 'Subir persiste el orden del guion');
    [$status] = hq_request($admin, 'mission_move', ['operation_id' => $id, 'mission_id' => $moveId, 'direction' => 'down'], true);
    [$unused, $ordered] = hq_request($admin, 'state', ['operation_id' => $id, 'channel' => 'alpha']);
    $ids = array_map('intval', array_column($ordered['missions'], 'id'));
    hq_check($status === 200 && array_search($moveId, $ids, true) > array_search($beforeId, $ids, true), 'Bajar persiste el orden del guion');
    [$status] = hq_request($alpha, 'mission_move', ['operation_id' => $id, 'mission_id' => $moveId, 'direction' => 'up'], true, true);
    hq_check($status === 403, 'El enlace no puede ordenar el guion');
    [$status] = hq_request($admin, 'mission_move', ['operation_id' => $id, 'mission_id' => $moveId, 'direction' => 'up', 'csrf_token' => 'incorrecto'], true);
    hq_check($status === 403, 'CSRF protege el orden del guion');
    [$status] = hq_request($admin, 'mission_move', ['operation_id' => $id, 'mission_id' => $moveId + 100000, 'direction' => 'up'], true);
    hq_check($status === 404, 'No se ordenan misiones ajenas al guion');
    [$status] = hq_request($admin, 'mission_remove', ['operation_id' => $id, 'mission_id' => $moveId], true);
    $query = $pdo->prepare('SELECT status FROM hq_missions WHERE id = ?'); $query->execute([$moveId]);
    hq_check($status === 200 && $query->fetchColumn() === 'deleted', 'La papelera retira una mision pendiente conservando historial');
    hq_request($admin, 'access', ['operation_id' => $id, 'team' => 'alpha'], true);
    [$status] = hq_request($alpha, 'state', ['operation_id' => $id, 'channel' => 'alpha'], false, true);
    hq_check($status === 401, 'Renovar código revoca la sesión anterior');
    hq_request($admin, 'settings', ['operation_id' => $id, 'title' => 'No cambiar el calendario', 'operation_date' => '2000-01-01', 'status' => 'closed'], true);
    [$status] = hq_request($bravo, 'message', ['operation_id' => $id, 'body' => 'No debe enviarse'], true, true);
    hq_check($status === 422, 'Cerrar la partida bloquea transmisiones');
    foreach (['mission_remove', 'mission_move'] as $closedAction) {
        [$status] = hq_request($admin, $closedAction, ['operation_id' => $id, 'mission_id' => $missionId, 'direction' => 'up'], true);
        hq_check($status === 422, 'Cerrar bloquea cambios del guion: ' . $closedAction);
    }
    [$status, $state] = hq_request($admin, 'state', ['operation_id' => $id, 'channel' => 'alpha']);
    hq_check($state['operation']['title'] === 'HQ TEST actualizado' && $state['operation']['operation_date'] === $today->format('Y-m-d'), 'Ajustes HQ no modifica el nombre ni la fecha del evento');
    $pdo->prepare('UPDATE events SET event_date = ? WHERE id = ?')->execute([$today->modify('-1 day')->format('Y-m-d'), $todayEvent]);
    [$status] = hq_request($bravo, 'state', ['operation_id' => $id, 'channel' => 'bravo'], false, true);
    hq_check($status === 410, 'Una partida que pasa al pasado deja de estar disponible para el enlace');
    [$status, $listed] = hq_request($admin, 'list');
    hq_check(!in_array($todayEvent, array_map('intval', array_column($listed['operations'], 'event_id')), true), 'La lista retira eventos cuya fecha ha cambiado al pasado');
    [$status, $future] = hq_request($admin, 'select', ['event_id' => $futureEvent], true);
    $futureId = (int) $future['operation_id'];
    $operations[] = $futureId;
    [$status, $otherMap] = hq_request($admin, 'map', ['operation_id' => $futureId]);
    hq_check($status === 200 && $otherMap['annotations'] === null, 'Cada partida tiene un mapa independiente');
    $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$futureEvent]);
    [$status] = hq_request($admin, 'state', ['operation_id' => $futureId, 'channel' => 'alpha']);
    hq_check($status === 410, 'Eliminar un evento retira su operación del HQ');
    $query = $pdo->prepare('SELECT event_id FROM hq_operations WHERE id = ?');
    $query->execute([$futureId]);
    hq_check($query->fetch()['event_id'] === null, 'Eliminar el evento conserva los datos HQ sin asignarlos a otro evento');
    echo $passed . " comprobaciones superadas.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FALLO: ' . $exception->getMessage() . PHP_EOL);
    $failed = true;
} finally {
    if (isset($rateIdentifier)) burnout_rate_limit_clear('hq_audit_test', $rateIdentifier);
    foreach ($operations as $id) {
        $pdo->prepare('DELETE FROM hq_operations WHERE id = ?')->execute([$id]);
        foreach (['alpha', 'bravo', 'hq-' . $adminId] as $sender) {
            $pdo->prepare('DELETE FROM rate_limits WHERE scope = ? AND identifier_hash = ?')->execute(['hq_message', burnout_rate_limit_hash($id . '|' . $sender . '|127.0.0.1')]);
            $pdo->prepare('DELETE FROM rate_limits WHERE scope = ? AND identifier_hash = ?')->execute(['hq_message', burnout_rate_limit_hash($id . '|' . $sender . '|::1')]);
        }
    }
    foreach ($events as $eventId) {
        $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$eventId]);
    }
    foreach (array_unique($catalogCreated) as $catalogId) {
        $pdo->prepare('DELETE FROM hq_mission_catalog WHERE id = ?')->execute([$catalogId]);
    }
    if ($adminId) { $pdo->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$adminId]); }
    foreach (array_unique(array_merge($sessions, [$admin['id'] ?? '', $alpha['id'] ?? '', $bravo['id'] ?? ''])) as $sessionId) {
        if ($sessionId === '') continue;
        session_id($sessionId);
        session_start();
        session_destroy();
    }
}
exit(!empty($failed) ? 1 : 0);
