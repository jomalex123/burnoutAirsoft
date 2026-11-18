<?php

declare(strict_types=1);

require_once __DIR__ . '/hq.php';

function burnout_hq_player_weights(): array
{
    return ['Fusilero' => 1, 'Tirador Selecto' => 2, 'Francotirador' => 2, 'Apoyo' => 3, 'Escudo' => 2, 'Sin especificar' => 1];
}

function burnout_hq_team_players(int $eventId, string $team): array
{
    $query = burnout_pdo()->prepare(
        'SELECT a.id, a.full_name AS name, COALESCE(a.weapon_role, ?) AS role,
                COALESCE(r.team_name, ?) AS team_name, a.tactical_role
         FROM registrations r JOIN registration_attendees a ON a.registration_id = r.id AND a.event_id = r.event_id
         WHERE r.event_id = ? AND r.hq_team = ? ORDER BY r.id, a.id'
    );
    $query->execute(['Sin especificar', '', $eventId, $team]);
    $players = $query->fetchAll();
    foreach ($players as &$player) $player['id'] = (int) $player['id'];
    unset($player);
    return ['team' => $team, 'count' => count($players), 'players' => $players];
}

function burnout_hq_set_player_role(int $operationId, string $team, array $session, array $input): array
{
    $playerId = burnout_hq_id($input, 'player_id');
    $role = burnout_hq_choice($input, 'tactical_role', ['', 'TL', 'Ingeniero', 'Radio Operator', 'Segundo TL', 'Medico']);
    $pdo = burnout_pdo();
    $pdo->beginTransaction();
    try {
        $operation = burnout_hq_operation($operationId, true);
        if ($operation['status'] !== 'active') throw new BurnoutUserException('La partida está cerrada.');
        $query = $pdo->prepare('SELECT code_hash FROM hq_access WHERE operation_id = ? AND team = ? FOR UPDATE');
        $query->execute([$operationId, $team]);
        $hash = $query->fetchColumn();
        if (!is_string($hash) || !hash_equals($hash, (string) ($session['version'] ?? ''))) {
            http_response_code(401);
            throw new BurnoutUserException('Accede de nuevo con el código de tu equipo.');
        }
        $query = $pdo->prepare('SELECT a.id FROM registration_attendees a JOIN registrations r ON r.id = a.registration_id AND r.event_id = a.event_id WHERE a.id = ? AND r.event_id = ? AND r.hq_team = ? FOR UPDATE');
        $query->execute([$playerId, $operation['event_id'], $team]);
        if (!$query->fetchColumn()) {
            http_response_code(403);
            throw new BurnoutUserException('El jugador ya no pertenece a tu equipo.');
        }
        if ($role !== '') {
            $query = $pdo->prepare('SELECT a.id FROM registration_attendees a JOIN registrations r ON r.id = a.registration_id AND r.event_id = a.event_id WHERE r.event_id = ? AND r.hq_team = ? AND a.tactical_role = ? AND a.id <> ? ORDER BY a.id FOR UPDATE');
            $query->execute([$operation['event_id'], $team, $role, $playerId]);
            $assigned = $query->fetchAll(PDO::FETCH_COLUMN);
            if ($role === 'Medico' && count($assigned) >= 2) {
                throw new BurnoutUserException('El equipo ya tiene dos médicos. Retira la función de uno antes de asignar otro.');
            }
            if ($role !== 'Medico') {
                $clear = $pdo->prepare('UPDATE registration_attendees SET tactical_role = NULL WHERE id = ?');
                foreach ($assigned as $assignedId) $clear->execute([$assignedId]);
            }
        }
        $query = $pdo->prepare('UPDATE registration_attendees SET tactical_role = ? WHERE id = ?');
        $query->execute([$role === '' ? null : $role, $playerId]);
        $roster = burnout_hq_team_players((int) $operation['event_id'], $team);
        $pdo->commit();
        return $roster;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

// Se usa dentro de la transacción de reparto, tras bloquear la operación.
// Al unir equipos se conservan primero las funciones de quienes ya estaban allí.
function burnout_hq_limit_player_roles(int $eventId, array $movedRegistrations): void
{
    $pdo = burnout_pdo();
    $query = $pdo->prepare('SELECT a.id, a.registration_id, a.tactical_role, r.hq_team FROM registration_attendees a JOIN registrations r ON r.id = a.registration_id AND r.event_id = a.event_id WHERE r.event_id = ? AND a.tactical_role IS NOT NULL ORDER BY a.id FOR UPDATE');
    $query->execute([$eventId]);
    $rows = $query->fetchAll();
    usort($rows, static function (array $a, array $b) use ($movedRegistrations): int {
        return ((int) in_array((int) $a['registration_id'], $movedRegistrations, true) <=> (int) in_array((int) $b['registration_id'], $movedRegistrations, true)) ?: ((int) $a['id'] <=> (int) $b['id']);
    });
    $counts = [];
    $clear = $pdo->prepare('UPDATE registration_attendees SET tactical_role = NULL WHERE id = ?');
    foreach ($rows as $row) {
        $key = $row['hq_team'] . ':' . $row['tactical_role'];
        $counts[$key] = ($counts[$key] ?? 0) + 1;
        if ($counts[$key] > ($row['tactical_role'] === 'Medico' ? 2 : 1)) $clear->execute([$row['id']]);
    }
}

function burnout_hq_players(int $eventId): array
{
    $query = burnout_pdo()->prepare(
        'SELECT r.id AS registration_id, r.team_name, r.hq_team, a.id, a.full_name, a.weapon_role
         FROM registrations r JOIN registration_attendees a ON a.registration_id = r.id AND a.event_id = r.event_id
         WHERE r.event_id = ? ORDER BY r.id, a.id'
    );
    $query->execute([$eventId]);
    $groups = [];
    $weights = burnout_hq_player_weights();
    foreach ($query->fetchAll() as $row) {
        $id = (int) $row['registration_id'];
        if (!isset($groups[$id])) {
            $groups[$id] = ['id' => $id, 'name' => (string) ($row['team_name'] ?? ''), 'team' => $row['hq_team'], 'points' => 0, 'players' => []];
        }
        $role = $row['weapon_role'] ?? 'Sin especificar';
        $points = $weights[$role] ?? 1;
        $groups[$id]['points'] += $points;
        $groups[$id]['players'][] = ['id' => (int) $row['id'], 'name' => $row['full_name'], 'role' => $role, 'points' => $points];
    }
    return array_values($groups);
}

// Vector: puntos, personas y cantidades por rol. Cada inscripción es indivisible.
function burnout_hq_group_vector(array $group): array
{
    $roles = array_keys(burnout_hq_player_weights());
    $vector = array_fill(0, count($roles) + 2, 0);
    foreach ($group['players'] as $player) {
        $role = array_search($player['role'], $roles, true);
        if ($role === false) $role = count($roles) - 1;
        $vector[0] += burnout_hq_player_weights()[$roles[$role]];
        $vector[1]++;
        $vector[$role + 2]++;
    }
    return $vector;
}

function burnout_hq_balance_score(array $difference): int
{
    $score = 4 * $difference[0] ** 2 + $difference[1] ** 2;
    foreach (array_values(burnout_hq_player_weights()) as $index => $weight) {
        $score += ($weight * $difference[$index + 2]) ** 2;
    }
    return $score;
}

function burnout_hq_vector_add(array $a, array $b, int $factor): array
{
    foreach ($a as $index => $value) $a[$index] = $value + $factor * $b[$index];
    return $a;
}

function burnout_hq_balance_players(array $groups): array
{
    if (!$groups) return [];
    $vectors = array_map('burnout_hq_group_vector', $groups);
    $best = [];
    $bestScore = PHP_INT_MAX;
    // Varias construcciones deterministas evitan depender del orden de inscripción.
    for ($seed = 0; $seed < 16; $seed++) {
        $order = array_keys($groups);
        usort($order, static function (int $a, int $b) use ($seed, $groups, $vectors): int {
            if ($seed === 0) return $vectors[$b][0] <=> $vectors[$a][0] ?: $groups[$a]['id'] <=> $groups[$b]['id'];
            return strcmp(hash('sha256', $seed . ':' . $groups[$a]['id']), hash('sha256', $seed . ':' . $groups[$b]['id']));
        });
        $difference = array_fill(0, 8, 0);
        $sides = [];
        foreach ($order as $index) {
            $alpha = burnout_hq_vector_add($difference, $vectors[$index], 1);
            $bravo = burnout_hq_vector_add($difference, $vectors[$index], -1);
            $side = burnout_hq_balance_score($alpha) <= burnout_hq_balance_score($bravo) ? 1 : -1;
            $sides[$index] = $side;
            $difference = $side === 1 ? $alpha : $bravo;
        }
        $score = burnout_hq_balance_score($difference);
        if ($score < $bestScore) { $bestScore = $score; $best = $sides; $bestDifference = $difference; }
    }
    // Mejorar el reparto moviendo o intercambiando grupos completos; trabajo acotado.
    for ($pass = 0; $pass < 8 && $bestScore > 0; $pass++) {
        $change = null;
        $nextScore = $bestScore;
        foreach ($vectors as $i => $vector) {
            $candidate = burnout_hq_vector_add($bestDifference, $vector, -2 * $best[$i]);
            $score = burnout_hq_balance_score($candidate);
            if ($score < $nextScore) { $nextScore = $score; $change = [$i]; $nextDifference = $candidate; }
        }
        $checked = 0;
        foreach ($vectors as $i => $vector) {
            foreach ($vectors as $j => $other) {
                if ($j <= $i || $best[$i] === $best[$j]) continue;
                if (++$checked > 30000) break 2;
                $candidate = burnout_hq_vector_add(burnout_hq_vector_add($bestDifference, $vector, -2 * $best[$i]), $other, -2 * $best[$j]);
                $score = burnout_hq_balance_score($candidate);
                if ($score < $nextScore) { $nextScore = $score; $change = [$i, $j]; $nextDifference = $candidate; }
            }
        }
        if ($change === null) break;
        foreach ($change as $index) $best[$index] *= -1;
        $bestScore = $nextScore;
        $bestDifference = $nextDifference;
    }
    $assignments = [];
    foreach ($groups as $index => $group) $assignments[$group['id']] = $best[$index] === 1 ? 'alpha' : 'bravo';
    return $assignments;
}

function burnout_hq_mutate_players(int $operationId, array $input, bool $automatic): array
{
    $pdo = burnout_pdo();
    $pdo->beginTransaction();
    try {
        $operation = burnout_hq_operation($operationId, true);
        if ($operation['status'] !== 'active') throw new BurnoutUserException('La partida está cerrada.');
        // El bloqueo del evento también serializa altas y borrados de inscripciones.
        $query = $pdo->prepare('SELECT id FROM events WHERE id = ? FOR UPDATE');
        $query->execute([$operation['event_id']]);
        $query = $pdo->prepare('SELECT id FROM registrations WHERE event_id = ? ORDER BY id FOR UPDATE');
        $query->execute([$operation['event_id']]);
        $groups = burnout_hq_players((int) $operation['event_id']);
        if ($automatic) {
            $assignments = burnout_hq_balance_players($groups);
        } else {
            $registrationId = burnout_hq_id($input, 'registration_id');
            $team = burnout_hq_choice($input, 'team', ['alpha', 'bravo']);
            if (!in_array($registrationId, array_column($groups, 'id'), true)) {
                throw new BurnoutUserException('La inscripción ya no pertenece a esta partida.');
            }
            $assignments = [$registrationId => $team];
        }
        $query = $pdo->prepare('UPDATE registrations SET hq_team = ? WHERE id = ? AND event_id = ?');
        $movedRegistrations = [];
        foreach ($groups as $group) {
            if (isset($assignments[$group['id']]) && $assignments[$group['id']] !== $group['team']) $movedRegistrations[] = $group['id'];
        }
        foreach ($assignments as $id => $team) $query->execute([$team, $id, $operation['event_id']]);
        burnout_hq_limit_player_roles((int) $operation['event_id'], $movedRegistrations);
        $groups = burnout_hq_players((int) $operation['event_id']);
        $pdo->commit();
        return $groups;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}
