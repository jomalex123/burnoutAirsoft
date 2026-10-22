<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/errors.php';

// Comprobaciones sin conexión a MySQL ni modificaciones de datos.
ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');

$cases = [
    [1615, 'HY000', 'problema temporal'],
    [1213, '40001', 'problema temporal'],
    [1205, 'HY000', 'problema temporal'],
    [1451, '23000', 'datos asociados'],
    [1062, '23000', 'mismos datos únicos'],
    [2002, 'HY000', 'comunicar con la base de datos'],
    [0, '08006', 'comunicar con la base de datos'],
    [1146, '42S02', 'base de datos no ha podido completar'],
];

foreach ($cases as [$driverCode, $state, $expected]) {
    $exception = new PDOException('SQLSTATE detalle_tecnico_privado');
    $exception->errorInfo = [$state, $driverCode, 'detalle_tecnico_privado'];
    $message = burnout_error_message($exception, 'borrar el evento');

    if (!str_contains($message, $expected)
        || !str_contains($message, 'borrar el evento')
        || !str_contains($message, 'Contacte con un administrador.')
        || str_contains($message, 'SQLSTATE')
        || str_contains($message, 'detalle_tecnico_privado')) {
        throw new RuntimeException('Mensaje incorrecto para el código ' . $driverCode);
    }
}

foreach ([new RuntimeException('detalle_tecnico_privado'), new Error('detalle_tecnico_privado')] as $exception) {
    $message = burnout_error_message($exception, 'guardar los cambios');

    if (str_contains($message, 'detalle_tecnico_privado') || !str_contains($message, 'problema interno')) {
        throw new RuntimeException('Se ha expuesto un error técnico.');
    }
}

$message = burnout_error_message(new BurnoutUserException('El evento tiene asistentes.'), 'borrar el evento');

if ($message !== 'Error al borrar el evento: El evento tiene asistentes.') {
    throw new RuntimeException('Se ha perdido el mensaje de validación.');
}

$_SERVER['SCRIPT_NAME'] = '/events.php';
ob_start();
burnout_handle_uncaught_exception(new RuntimeException('detalle_tecnico_privado'));
$payload = json_decode((string) ob_get_clean(), true);

if (http_response_code() !== 500 || !isset($payload['error'])
    || str_contains($payload['error'], 'detalle_tecnico_privado')) {
    throw new RuntimeException('Respuesta JSON inesperada para una excepción sin capturar.');
}

$_SERVER['SCRIPT_NAME'] = '/registro.php';
ob_start();
burnout_handle_uncaught_exception(new RuntimeException('detalle_tecnico_privado'));
$html = (string) ob_get_clean();

if (http_response_code() !== 500 || !str_contains($html, '<!DOCTYPE html>')
    || !str_contains($html, 'Contacte con un administrador.')
    || str_contains($html, 'detalle_tecnico_privado')) {
    throw new RuntimeException('Respuesta HTML inesperada para una excepción sin capturar.');
}

echo "Mensajes de error: 13 comprobaciones correctas." . PHP_EOL;
