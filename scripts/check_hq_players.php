<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/hq_players.php';

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function players_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo 'OK: ' . $message . PHP_EOL;
}

function players_group(int $id, array $roles): array
{
    return ['id' => $id, 'players' => array_map(static function (string $role): array { return ['role' => $role]; }, $roles)];
}

players_check(burnout_hq_balance_players([]) === [], 'Sin jugadores');
$single = [players_group(1, ['Tirador Selecto', 'Tirador Selecto', 'Tirador Selecto', 'Tirador Selecto', 'Fusilero'])];
players_check(count(burnout_hq_balance_players($single)) === 1, 'Una inscripción de cinco jugadores nunca se divide');
players_check(burnout_hq_group_vector($single[0])[0] === 9, 'Cuatro tiradores y un fusilero suman nueve puntos');
$groups = [$single[0], players_group(2, array_fill(0, 9, 'Fusilero'))];
$assignment = burnout_hq_balance_players($groups);
players_check($assignment[1] !== $assignment[2], 'Admite equipos con distinto número de jugadores para compensar puntos');
$roles = array_keys(burnout_hq_player_weights());
$groups = [];
foreach ($roles as $index => $role) {
    $groups[] = players_group($index * 2 + 1, [$role]);
    $groups[] = players_group($index * 2 + 2, [$role]);
}
$assignment = burnout_hq_balance_players($groups);
$difference = array_fill(0, 8, 0);
foreach ($groups as $group) $difference = burnout_hq_vector_add($difference, burnout_hq_group_vector($group), $assignment[$group['id']] === 'alpha' ? 1 : -1);
players_check(burnout_hq_balance_score($difference) === 0, 'Reparto exacto de roles, puntos y jugadores cuando las parejas lo permiten');
players_check(burnout_hq_balance_players($groups) === $assignment, 'Resultado reproducible');
players_check(burnout_hq_group_vector(players_group(1, ['Sin especificar']))[0] === 1, 'Sin rol conserva su etiqueta y cuenta un punto');
$groups = [];
for ($i = 1; $i <= 80; $i++) {
    $groupRoles = [];
    for ($j = 0; $j < 1 + $i % 10; $j++) $groupRoles[] = $roles[($i + $j) % count($roles)];
    $groups[] = players_group($i, $groupRoles);
}
$assignment = burnout_hq_balance_players($groups);
players_check(count($assignment) === count($groups) && !array_diff(array_keys($assignment), array_column($groups, 'id')), 'Todas las inscripciones conservadas en un reparto grande');
players_check(count(array_unique($assignment)) === 2, 'El reparto grande utiliza ambos equipos');
