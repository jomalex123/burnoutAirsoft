<?php

declare(strict_types=1);

// Solo esta clase identifica mensajes preparados para mostrarse al usuario.
class BurnoutUserException extends RuntimeException
{
}

// Última protección para fallos inesperados en las peticiones web.
if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    set_exception_handler('burnout_handle_uncaught_exception');
}

function burnout_handle_uncaught_exception(Throwable $exception): void
{
    $message = burnout_error_message($exception, 'procesar la solicitud');
    http_response_code(500);

    if (headers_sent()) {
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        return;
    }

    header('Cache-Control: no-store');
    $endpoint = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    if (in_array($endpoint, ['events.php', 'gallery_items.php', 'instagram_feed.php'], true)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Error - Burnout Airsoft</title>'
        . '<link rel="stylesheet" href="assets/css/style.css">'
        . '<link rel="stylesheet" href="assets/css/registro.css"></head>'
        . '<body><main class="ms-container registro-page"><section class="ms-section__block">'
        . '<h1>No se ha podido completar la solicitud</h1><p>'
        . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
        . '</p><a href="./">Volver al inicio</a></section></main></body></html>';
}

function burnout_error_message(Throwable $exception, string $operation): string
{
    if ($exception instanceof BurnoutUserException) {
        return 'Error al ' . $operation . ': ' . $exception->getMessage();
    }

    error_log(sprintf('[Burnout: %s] %s', $operation, (string) $exception));

    $reason = 'se ha producido un problema interno';

    if ($exception instanceof PDOException) {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        if (in_array($driverCode, [1615, 1205, 1213], true)) {
            $reason = 'la base de datos no ha podido completar la operación por un problema temporal';
        } elseif ($driverCode === 1451) {
            $reason = 'existen datos asociados que impiden completar la operación';
        } elseif ($driverCode === 1062) {
            $reason = 'ya existe un elemento con los mismos datos únicos';
        } elseif (substr($sqlState, 0, 2) === '08' || in_array($driverCode, [1040, 2002, 2003, 2006, 2013], true)) {
            $reason = 'no se ha podido comunicar con la base de datos';
        } else {
            $reason = 'la base de datos no ha podido completar la operación';
        }
    }

    return 'Error al ' . $operation . ': ' . $reason . '. Contacte con un administrador.';
}
