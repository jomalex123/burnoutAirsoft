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

    burnout_pdo()->exec(
        'CREATE TABLE IF NOT EXISTS rate_limits (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope VARCHAR(80) NOT NULL,
            identifier_hash CHAR(64) NOT NULL,
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            window_started_at DATETIME NOT NULL,
            blocked_until DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY rate_limits_scope_identifier_unique (scope, identifier_hash),
            KEY rate_limits_blocked_until_index (blocked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

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
    $identifierHash = burnout_rate_limit_hash($identifier);
    $now = date('Y-m-d H:i:s');
    $statement = $pdo->prepare(
        'SELECT id, attempts, window_started_at, blocked_until
         FROM rate_limits
         WHERE scope = :scope AND identifier_hash = :identifier_hash
         LIMIT 1'
    );
    $statement->execute([
        'scope' => $scope,
        'identifier_hash' => $identifierHash,
    ]);
    $row = $statement->fetch();

    if ($row && !empty($row['blocked_until'])) {
        $remaining = burnout_rate_limit_seconds_until((string) $row['blocked_until']);

        if ($remaining > 0) {
            return $remaining;
        }
    }

    $attempts = 1;
    $windowStartedAt = $now;

    if ($row) {
        $windowAge = time() - (strtotime((string) $row['window_started_at']) ?: 0);

        if ($windowAge <= $windowSeconds) {
            $attempts = (int) $row['attempts'] + 1;
            $windowStartedAt = (string) $row['window_started_at'];
        }
    }

    $blockedUntil = $attempts >= $maxAttempts ? date('Y-m-d H:i:s', time() + $blockSeconds) : null;

    if ($row) {
        $update = $pdo->prepare(
            'UPDATE rate_limits
             SET attempts = :attempts, window_started_at = :window_started_at, blocked_until = :blocked_until
             WHERE id = :id'
        );
        $update->execute([
            'attempts' => $attempts,
            'window_started_at' => $windowStartedAt,
            'blocked_until' => $blockedUntil,
            'id' => (int) $row['id'],
        ]);
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO rate_limits (scope, identifier_hash, attempts, window_started_at, blocked_until)
             VALUES (:scope, :identifier_hash, :attempts, :window_started_at, :blocked_until)'
        );
        $insert->execute([
            'scope' => $scope,
            'identifier_hash' => $identifierHash,
            'attempts' => $attempts,
            'window_started_at' => $windowStartedAt,
            'blocked_until' => $blockedUntil,
        ]);
    }

    return $blockedUntil ? $blockSeconds : 0;
}

function burnout_rate_limit_clear(string $scope, string $identifier): void
{
    burnout_rate_limit_table();

    $statement = burnout_pdo()->prepare(
        'DELETE FROM rate_limits WHERE scope = :scope AND identifier_hash = :identifier_hash'
    );
    $statement->execute([
        'scope' => $scope,
        'identifier_hash' => burnout_rate_limit_hash($identifier),
    ]);
}

