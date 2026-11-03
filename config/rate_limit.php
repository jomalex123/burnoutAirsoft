<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

function burnout_client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
}

function burnout_rate_limit_table(): void
{
    static $ready = false;

    if ($ready) {
        return;
    }

    // Las tablas se crean desde database/schema.sql por un administrador SQL.
    $ready = true;
}

function burnout_rate_limit_hash(string $identifier): string
{
    return hash('sha256', strtolower(trim($identifier)));
}

function burnout_rate_limit_seconds_until(string $dateTime): int
{
    $target = strtotime($dateTime);

    if ($target === false) {
        return 0;
    }

    return max(0, $target - time());
}

function burnout_rate_limit_block_seconds(string $scope, string $identifier): int
{
    burnout_rate_limit_table();

    $statement = burnout_pdo()->prepare(
        'SELECT blocked_until
         FROM rate_limits
         WHERE scope = :scope AND identifier_hash = :identifier_hash
         LIMIT 1'
    );
    $statement->execute([
        'scope' => $scope,
        'identifier_hash' => burnout_rate_limit_hash($identifier),
    ]);
    $row = $statement->fetch();

    if (!$row || empty($row['blocked_until'])) {
        return 0;
    }

    return burnout_rate_limit_seconds_until((string) $row['blocked_until']);
}

function burnout_rate_limit_hit(string $scope, string $identifier, int $maxAttempts, int $windowSeconds, int $blockSeconds): int
{
    burnout_rate_limit_table();
    $pdo = burnout_pdo();
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $identifierHash = burnout_rate_limit_hash($identifier);
        $now = date('Y-m-d H:i:s');
        // Crear o bloquear la fila antes de contar evita perder intentos concurrentes.
        $seed = $pdo->prepare('INSERT INTO rate_limits (scope, identifier_hash, attempts, window_started_at) VALUES (?, ?, 0, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
        $seed->execute([$scope, $identifierHash, $now]);
        $statement = $pdo->prepare('SELECT id, attempts, window_started_at, blocked_until FROM rate_limits WHERE scope = ? AND identifier_hash = ? FOR UPDATE');
        $statement->execute([$scope, $identifierHash]);
        $row = $statement->fetch();
        $remaining = empty($row['blocked_until']) ? 0 : burnout_rate_limit_seconds_until((string) $row['blocked_until']);
        if ($remaining > 0) {
            if ($ownsTransaction) $pdo->commit();
            return $remaining;
        }
        $windowAge = time() - (strtotime((string) $row['window_started_at']) ?: 0);
        $inWindow = $windowAge <= $windowSeconds;
        $attempts = $inWindow ? (int) $row['attempts'] + 1 : 1;
        $windowStartedAt = $inWindow ? $row['window_started_at'] : $now;
        $blockedUntil = $attempts >= $maxAttempts ? date('Y-m-d H:i:s', time() + $blockSeconds) : null;
        $update = $pdo->prepare('UPDATE rate_limits SET attempts = ?, window_started_at = ?, blocked_until = ? WHERE id = ?');
        $update->execute([$attempts, $windowStartedAt, $blockedUntil, $row['id']]);
        if ($ownsTransaction) $pdo->commit();
        return $blockedUntil ? $blockSeconds : 0;
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}

function burnout_rate_limit_clear(string $scope, string $identifier): void
{
    burnout_rate_limit_table();
    $statement = burnout_pdo()->prepare('DELETE FROM rate_limits WHERE scope = ? AND identifier_hash = ?');
    $statement->execute([$scope, burnout_rate_limit_hash($identifier)]);
}
