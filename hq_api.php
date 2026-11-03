<?php

declare(strict_types=1);

require_once __DIR__ . '/config/hq.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'POST'], true)) {
        http_response_code(405);
        header('Allow: GET, POST');
        throw new BurnoutUserException('Método no permitido.');
    }
    $input = $method === 'POST' ? $_POST : $_GET;
    $action = burnout_hq_choice($input, 'action', ['login', 'logout', 'list', 'state', 'select', 'settings', 'access', 'mission', 'mission_update', 'mission_status', 'ack', 'message', 'catalog', 'catalog_save', 'catalog_remove', 'map', 'map_save', 'mission_move', 'mission_remove']);
    if (($method === 'GET') !== in_array($action, ['list', 'state', 'catalog', 'map'], true)) {
        http_response_code(405);
        throw new BurnoutUserException('Método no permitido.');
    }
    burnout_start_session();
    if ($method === 'POST' && !burnout_check_csrf(is_string($input['csrf_token'] ?? null) ? $input['csrf_token'] : null)) {
        http_response_code(403);
        throw new BurnoutUserException('La sesión ha caducado. Recarga la página.');
    }
    $id = burnout_hq_id($input, 'operation_id', true);
    $teamView = ($input['view'] ?? '') === 'team';
    $admin = $teamView ? null : burnout_current_admin();
    $result = [];
    if ($action === 'login') {
        $identifier = burnout_client_ip();
        if (burnout_rate_limit_block_seconds('hq_login', $identifier) > 0) {
            http_response_code(429);
            throw new BurnoutUserException('Demasiados intentos. Espera quince minutos.');
        }
        // Reservar el intento antes de las verificaciones de hash, tambien entre sesiones.
        if (burnout_rate_limit_hit('hq_login', $identifier, 9, 900, 900) > 0) {
            http_response_code(429);
            throw new BurnoutUserException('Demasiados intentos. Espera quince minutos.');
        }
        $code = burnout_hq_text($input, 'code', 100);
        if (!preg_match('/^[A-F0-9]{12}$/D', $code)) {
            http_response_code(403);
            throw new BurnoutUserException('Acceso no válido o partida cerrada. Consulta con HQ.');
        }
        $query = burnout_pdo()->prepare("SELECT a.operation_id, a.team, a.code_hash FROM hq_access a JOIN hq_operations o ON o.id = a.operation_id JOIN events e ON e.id = o.event_id WHERE o.status = 'active' AND e.event_date >= ?");
        $query->execute([burnout_hq_today()]);
        $matches = [];
        foreach ($query->fetchAll() as $access) {
            if ($id > 0 && (int) $access['operation_id'] !== $id) continue;
            if (isset($input['team']) && $access['team'] !== $input['team']) continue;
            if (password_verify($code, $access['code_hash'])) $matches[] = $access;
        }
        if (count($matches) !== 1) {
            http_response_code(403);
            throw new BurnoutUserException('Acceso no válido o partida cerrada. Consulta con HQ.');
        }
        $id = (int) $matches[0]['operation_id'];
        $team = $matches[0]['team'];
        $hash = $matches[0]['code_hash'];
        session_regenerate_id(true);
        $_SESSION['hq_link'] = ['operation_id' => $id, 'team' => $team, 'version' => $hash];
        burnout_rate_limit_clear('hq_login', $identifier);
        $result = ['team' => $team, 'operation_id' => $id];
    } elseif ($action === 'logout') {
        unset($_SESSION['hq_link']);
    } else {
        if ($teamView && !$id) $id = (int) ($_SESSION['hq_link']['operation_id'] ?? 0);
        $team = $admin ? null : burnout_hq_team_access($_SESSION['hq_link'] ?? [], $id);
        if (!$admin && !$team) {
            http_response_code(401);
            throw new BurnoutUserException('Accede de nuevo con el código de tu equipo.');
        }
        if (!$admin && !in_array($action, ['state', 'ack', 'message'], true)) {
            http_response_code(403);
            throw new BurnoutUserException('Esta acción corresponde a HQ.');
        }
        session_write_close();
        $pdo = burnout_pdo();
        if ($action === 'list') {
            $result['operations'] = burnout_hq_available_events();
        } elseif ($action === 'mission_move' || $action === 'mission_remove') {
            $missionId = burnout_hq_id($input, 'mission_id');
            $pdo->beginTransaction();
            $operation = burnout_hq_operation($id, true);
            if ($operation['status'] !== 'active') throw new BurnoutUserException('La partida está cerrada.');
            $query = $pdo->prepare("SELECT id FROM hq_missions WHERE operation_id = ? AND status <> 'deleted' ORDER BY sort_order ASC, id DESC FOR UPDATE");
            $query->execute([$id]);
            $ids = array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
            $index = array_search($missionId, $ids, true);
            if ($index === false) { http_response_code(404); throw new BurnoutUserException('La mision ya no esta disponible.'); }
            if ($action === 'mission_remove') {
                $query = $pdo->prepare("UPDATE hq_missions SET status = 'deleted' WHERE id = ? AND operation_id = ?");
                $query->execute([$missionId, $id]);
            } else {
                $direction = burnout_hq_choice($input, 'direction', ['up', 'down']);
                $target = $index + ($direction === 'up' ? -1 : 1);
                if (isset($ids[$target])) [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
                $query = $pdo->prepare('UPDATE hq_missions SET sort_order = ? WHERE id = ? AND operation_id = ?');
                foreach ($ids as $position => $mission) $query->execute([$position, $mission, $id]);
            }
            $pdo->commit();
        } elseif ($action === 'map' || $action === 'map_save') {
            burnout_hq_operation($id);
            if ($action === 'map_save') {
                $image = $input['annotations'] ?? '';
                if (!is_string($image) || strlen($image) > 4000000 || !str_starts_with($image, 'data:image/png;base64,')) throw new BurnoutUserException('El mapa no es valido o supera el limite permitido.');
                $binary = base64_decode(substr($image, 22), true);
                $size = $binary === false ? false : @getimagesizefromstring($binary);
                if (!$size || $size[2] !== IMAGETYPE_PNG || $size[0] < 1 || $size[1] < 1 || $size[0] > 2000 || $size[1] > 2000) throw new BurnoutUserException('Las anotaciones deben ser una imagen PNG del plano.');
                $query = $pdo->prepare('INSERT INTO hq_maps (operation_id, annotations) VALUES (?, ?) ON DUPLICATE KEY UPDATE annotations = VALUES(annotations)');
                $query->execute([$id, $binary]);
            } else {
                $query = $pdo->prepare('SELECT annotations FROM hq_maps WHERE operation_id = ?');
                $query->execute([$id]);
                $image = $query->fetchColumn();
                $result['annotations'] = $image === false ? null : 'data:image/png;base64,' . base64_encode($image);
            }
        } elseif ($action === 'catalog') {
            $result['missions'] = burnout_hq_catalog();
        } elseif ($action === 'catalog_save') {
            $catalogId = burnout_hq_id($input, 'catalog_id', true);
            $title = burnout_hq_text($input, 'title', 180);
            $briefing = burnout_hq_text($input, 'briefing', 8000);
            $classification = [];
            foreach (['category'] as $field) {
                $value = $input[$field] ?? '';
                if (!is_string($value)) throw new BurnoutUserException('Revisa la categoria de la mision.');
                $classification[$field] = trim($value) === '' ? '' : burnout_hq_text($input, $field, 80);
            }
            $pdo->beginTransaction();
            if ($catalogId) {
                $query = $pdo->prepare('SELECT id FROM hq_mission_catalog WHERE id = ? AND is_available = 1 FOR UPDATE');
                $query->execute([$catalogId]);
                if (!$query->fetchColumn()) { http_response_code(404); throw new BurnoutUserException('La misión ya no está disponible en el catálogo.'); }
                $hash = hash('sha256', $title . "\n" . $briefing);
                $query = $pdo->prepare('SELECT id FROM hq_mission_catalog WHERE content_hash = ? AND id <> ?');
                $query->execute([$hash, $catalogId]);
                if ($query->fetchColumn()) {
                    throw new BurnoutUserException('Ya existe una misión con ese nombre y guion en el catálogo.');
                }
                $query = $pdo->prepare('UPDATE hq_mission_catalog SET title = ?, briefing = ?, content_hash = ? WHERE id = ?');
                $query->execute([$title, $briefing, $hash, $catalogId]);
            } else {
                $catalogId = burnout_hq_catalog_store($title, $briefing);
            }
            $query = $pdo->prepare('UPDATE hq_mission_catalog SET category = ? WHERE id = ?');
            $query->execute([$classification['category'], $catalogId]);
            $pdo->commit();
            $result['catalog_id'] = $catalogId;
        } elseif ($action === 'catalog_remove') {
            $catalogId = burnout_hq_id($input, 'catalog_id', true);
            burnout_hq_catalog_item($catalogId);
            $query = $pdo->prepare('UPDATE hq_mission_catalog SET is_available = 0 WHERE id = ?');
            $query->execute([$catalogId]);
        } elseif ($action === 'select') {
            $eventId = burnout_hq_id($input, 'event_id');
            $result['operation_id'] = burnout_hq_select_event($eventId);
        } elseif ($action === 'state') {
            $channel = $team ?? burnout_hq_choice($input, 'channel', ['alpha', 'bravo']);
            $after = burnout_hq_id($input, 'after', true);
            $result = burnout_hq_state($id, $team, $channel, $after);
        } else {
            $operation = burnout_hq_operation($id);
            if ($action === 'settings') {
                $query = $pdo->prepare('UPDATE hq_operations SET status = ? WHERE id = ?');
                $query->execute([burnout_hq_choice($input, 'status', ['active', 'closed']), $id]);
            } elseif ($action === 'access') {
                $channel = burnout_hq_choice($input, 'team', ['alpha', 'bravo']);
                $code = strtoupper(bin2hex(random_bytes(6)));
                $query = $pdo->prepare('INSERT INTO hq_access (operation_id, team, code_hash) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE code_hash = VALUES(code_hash)');
                $query->execute([$id, $channel, password_hash($code, PASSWORD_DEFAULT)]);
                $result = ['code' => $code, 'team' => $channel];
            } else {
                if ($action === 'message') {
                    $identifier = $id . '|' . ($team ?? 'hq-' . $admin['id']) . '|' . burnout_client_ip();
                    if (burnout_rate_limit_hit('hq_message', $identifier, 20, 10, 10) > 0) {
                        http_response_code(429);
                        throw new BurnoutUserException('Espera unos segundos antes de transmitir de nuevo.');
                    }
                }
                $pdo->beginTransaction();
                $operation = burnout_hq_operation($id, true);
                if ($operation['status'] !== 'active') {
                    throw new BurnoutUserException('La partida está cerrada. Reábrela desde Ajustes para continuar.');
                }
                if ($action === 'message') {
                    $channel = $team ?? burnout_hq_choice($input, 'team', ['alpha', 'bravo']);
                    $body = burnout_hq_text($input, 'body', 1500);
                    $query = $pdo->prepare('INSERT INTO hq_messages (operation_id, team, sender, body) VALUES (?, ?, ?, ?)');
                    $query->execute([$id, $channel, $team ?? 'hq', $body]);
                } elseif ($action === 'mission' || $action === 'mission_update') {
                    $existing = null;
                    $missionId = burnout_hq_id($input, 'mission_id', $action !== 'mission_update');
                    if ($action === 'mission_update') {
                        $query = $pdo->prepare('SELECT is_imported FROM hq_missions WHERE id = ? AND operation_id = ? FOR UPDATE');
                        $query->execute([$missionId, $id]);
                        $existing = $query->fetch();
                        if (!$existing) {
                            http_response_code(404);
                            throw new BurnoutUserException('La misión ya no está disponible.');
                        }
                    }
                    $catalogId = burnout_hq_id($input, 'catalog_id', true);
                    $imported = $catalogId > 0;
                    if ($imported) {
                        $item = burnout_hq_catalog_item($catalogId);
                        $title = $item['title'];
                        $briefing = $item['briefing'];
                    } else {
                        if (!empty($existing['is_imported'])) {
                            throw new BurnoutUserException('Esta misión está importada. Selecciona otra misión del catálogo para sustituirla.');
                        }
                        $title = burnout_hq_text($input, 'title', 180);
                        $briefing = burnout_hq_text($input, 'briefing', 8000);
                        $catalogId = burnout_hq_catalog_store($title, $briefing);
                    }
                    if ($action === 'mission') {
                        $channel = burnout_hq_choice($input, 'team', ['alpha', 'bravo']);
                        $status = 'draft';
                        $query = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM hq_missions WHERE operation_id = ?');
                        $query->execute([$id]);
                        $position = (int) $query->fetchColumn();
                        $query = $pdo->prepare('INSERT INTO hq_missions (operation_id, team, title, briefing, status, catalog_id, is_imported, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                        $query->execute([$id, $channel, $title, $briefing, $status, $catalogId, (int) $imported, $position]);
                    } else {
                        $query = $pdo->prepare('UPDATE hq_missions SET title = ?, briefing = ?, catalog_id = ?, is_imported = ?, acknowledged_at = NULL WHERE id = ? AND operation_id = ?');
                        $query->execute([$title, $briefing, $catalogId, (int) $imported, $missionId, $id]);
                    }
                    $pdo->commit();
                    $result['catalog_id'] = $catalogId;
                } else {
                    $missionId = (int) ($input['mission_id'] ?? 0);
                    if ($action === 'ack') {
                        if (!$team) {
                            throw new BurnoutUserException('Solo el enlace del equipo puede confirmar la recepción.');
                        }
                        $query = $pdo->prepare("UPDATE hq_missions SET acknowledged_at = COALESCE(acknowledged_at, CURRENT_TIMESTAMP) WHERE id = ? AND operation_id = ? AND team = ? AND status = 'active'");
                        $query->execute([$missionId, $id, $team]);
                    } else {
                        $status = burnout_hq_choice($input, 'status', ['active', 'completed', 'failed', 'delete', 'reset']);
                        $status = $status === 'delete' ? 'deleted' : $status;
                        $status = $status === 'reset' ? 'draft' : $status;
                        // La operacion ya esta bloqueada antes de tocar sus misiones.
                        $query = $pdo->prepare('SELECT team, status FROM hq_missions WHERE id = ? AND operation_id = ? FOR UPDATE');
                        $query->execute([$missionId, $id]);
                        $mission = $query->fetch();
                        if (!$mission) {
                            http_response_code(404);
                            throw new BurnoutUserException('La mision ya no esta disponible.');
                        }
                        if ($status === 'active') {
                            if ($mission['status'] !== 'draft') throw new BurnoutUserException('Solo se puede iniciar una mision pendiente.');
                            $query = $pdo->prepare("SELECT id FROM hq_missions WHERE operation_id = ? AND team = ? AND status = 'active' LIMIT 1 FOR UPDATE");
                            $query->execute([$id, $mission['team']]);
                            if ($query->fetchColumn()) throw new BurnoutUserException('Este equipo ya tiene una mision en curso. Finalizala antes de iniciar otra.');
                        } elseif ($status !== 'draft' && $mission['status'] !== 'active') {
                            throw new BurnoutUserException('Solo se puede finalizar o eliminar una mision en curso.');
                        }
                        $query = $pdo->prepare("UPDATE hq_missions SET status = ?, acknowledged_at = CASE WHEN ? IN ('active', 'draft') THEN NULL ELSE acknowledged_at END WHERE id = ? AND operation_id = ?");
                        $query->execute([$status, $status, $missionId, $id]);
                        $pdo->commit();
                    }
                }
            }
        }
    }
    if (isset($pdo) && $pdo->inTransaction()) $pdo->commit();
    echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (BurnoutUserException $exception) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    if (http_response_code() < 400) {
        http_response_code(422);
    }
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[HQ] ' . (string) $exception);
    http_response_code(503);
    echo json_encode(['error' => 'No se puede conectar con HQ. Si acabas de instalarlo, comprueba la migración de base de datos.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
