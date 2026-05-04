<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

function burnout_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function burnout_current_admin(): ?array
{
    burnout_start_session();

    if (empty($_SESSION['admin_user_id'])) {
        return null;
    }

    $statement = burnout_pdo()->prepare(
        'SELECT id, username, display_name, role FROM admin_users WHERE id = :id AND is_active = 1 LIMIT 1'
    );
    $statement->execute(['id' => $_SESSION['admin_user_id']]);
    $user = $statement->fetch();

    return $user ?: null;
}

function burnout_login(string $username, string $password): bool
{
    $statement = burnout_pdo()->prepare(
        'SELECT id, username, password_hash FROM admin_users WHERE username = :username AND is_active = 1 LIMIT 1'
    );
    $statement->execute(['username' => $username]);
    $user = $statement->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        burnout_record_login_attempt($username, false);
        return false;
    }

    burnout_start_session();
    session_regenerate_id(true);
    $_SESSION['admin_user_id'] = (int) $user['id'];

    $update = burnout_pdo()->prepare('UPDATE admin_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id');
    $update->execute(['id' => $user['id']]);
    burnout_record_login_attempt($username, true);

    return true;
}

function burnout_record_login_attempt(string $username, bool $success): void
{
    try {
        $statement = burnout_pdo()->prepare(
            'INSERT INTO admin_login_audit (username, success, ip_address, user_agent)
             VALUES (:username, :success, :ip_address, :user_agent)'
        );
        $statement->execute([
            'username' => substr($username, 0, 80),
            'success' => $success ? 1 : 0,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
        ]);
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
    }
}

function burnout_logout(): void
{
    burnout_start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}

function burnout_csrf_token(): string
{
    burnout_start_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function burnout_check_csrf(?string $token): bool
{
    burnout_start_session();

    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function burnout_set_admin_flash(string $type, string $message): void
{
    burnout_start_session();

    $_SESSION['admin_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function burnout_pull_admin_flash(): ?array
{
    burnout_start_session();

    $flash = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);

    if (!is_array($flash) || empty($flash['type']) || empty($flash['message'])) {
        return null;
    }

    return [
        'type' => (string) $flash['type'],
        'message' => (string) $flash['message'],
    ];
}
