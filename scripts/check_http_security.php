<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/hq.php';
$config = burnout_database_config();
$environment = getenv('BURNOUT_ENV') ?: (burnout_env_config()['default'] ?? 'local');
if ($environment !== 'local' || !in_array($config['host'], ['127.0.0.1', 'localhost'], true)) {
    exit("Esta comprobación solo se permite en local.\n");
}

function security_http(string $path, ?array $post = null): array
{
    $headers = [];
    $curl = curl_init('http://localhost/burnoutAirsoft/' . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
            return strlen($line);
        },
    ]);
    if ($post !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    $body = curl_exec($curl);
    if ($body === false) throw new RuntimeException('No se puede conectar con Apache local.');
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return [$status, $headers, $body];
}

$checks = 0;
function security_check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
    echo 'OK · ' . $message . PHP_EOL;
}

try {
    [$status, $headers] = security_http('hq_equipo.php');
    security_check($status === 200, 'Pantalla de enlace disponible sin sesión');
    security_check(str_contains($headers['cache-control'] ?? '', 'no-store'), 'La pantalla de enlace no se almacena en caché');
    security_check(($headers['x-content-type-options'] ?? '') === 'nosniff', 'Cabecera nosniff');
    security_check(($headers['x-frame-options'] ?? '') === 'SAMEORIGIN', 'Protección frente a marcos externos');
    security_check(str_contains($headers['content-security-policy'] ?? '', "object-src 'none'") && str_contains($headers['content-security-policy'] ?? '', "frame-ancestors 'self'"), 'CSP aplicada por Apache');
    security_check(!isset($headers['x-powered-by']), 'La respuesta no publica X-Powered-By');
    security_check(str_contains(strtolower($headers['set-cookie'] ?? ''), 'httponly') && str_contains(strtolower($headers['set-cookie'] ?? ''), 'samesite=lax'), 'Cookie de sesión HttpOnly y SameSite');
    foreach (['AGENTS.md', '.git/config', 'config/hq.php', 'database/schema.sql', 'scripts/check_hq.php'] as $path) {
        [$status] = security_http($path);
        security_check(in_array($status, [403, 404], true), 'Ruta privada bloqueada: ' . $path);
    }
    [$status, $headers, $body] = security_http('hq_api.php?action=catalog&view=admin');
    security_check($status === 401 && str_contains($headers['content-type'] ?? '', 'application/json'), 'La API no publica el catálogo sin autenticación');
    security_check(!str_contains($body, 'SQLSTATE') && !str_contains($body, 'Stack trace'), 'Respuesta de error sin detalles internos');
    [$status] = security_http('hq_api.php?action=mission_status');
    security_check($status === 405, 'GET no modifica misiones');
    [$status] = security_http('hq_api.php', ['action' => 'state']);
    security_check($status === 405, 'La API exige el método de lectura correcto');
    [$status] = security_http('hq_api.php', ['action' => 'login', 'code' => 'INVALIDO']);
    security_check($status === 403, 'CSRF también protege el acceso del enlace');
    echo $checks . " comprobaciones HTTP superadas.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FALLO: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
