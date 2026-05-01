<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script solo se puede ejecutar por consola.\n");
    exit(1);
}

$username = $argv[1] ?? '';
$password = $argv[2] ?? '';
$displayName = $argv[3] ?? $username;

if ($username === '' || $password === '') {
    fwrite(STDERR, "Uso: php scripts/create_admin_user.php usuario contrasena [nombre]\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$statement = burnout_pdo()->prepare(
    'INSERT INTO admin_users (username, password_hash, display_name)
     VALUES (:username, :password_hash, :display_name)
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), display_name = VALUES(display_name), is_active = 1'
);
$statement->execute([
    'username' => $username,
    'password_hash' => $hash,
    'display_name' => $displayName,
]);

echo "Usuario administrador creado o actualizado: {$username}\n";
