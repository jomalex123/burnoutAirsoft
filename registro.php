<?php

declare(strict_types=1);

function registro_param(string $key): string
{
    return trim((string) ($_GET[$key] ?? ''));
}

function registro_format_date(string $value): string
{
    $date = DateTime::createFromFormat('Y-m-d', $value);

    if (!$date || $date->format('Y-m-d') !== $value) {
        return strtoupper($value);
    }

    $weekdays = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
    $months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    return strtoupper(sprintf(
        '%s %d de %s de %d',
        $weekdays[(int) $date->format('w')],
        (int) $date->format('j'),
        $months[(int) $date->format('n') - 1],
        (int) $date->format('Y')
    ));
}

function registro_format_turn(string $value): string
{
    $turn = strtolower($value);

    if ($turn === 'mañana' || $turn === 'maã±ana') {
        $turn = 'manana';
    }

    return strtoupper($turn);
}

function registro_replace_between_id(string $html, string $id, string $value): string
{
    $escaped = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $pattern = sprintf('/(<[^>]+id="%s"[^>]*>)(.*?)(<\/[^>]+>)/s', preg_quote($id, '/'));

    return preg_replace($pattern, '$1' . $escaped . '$3', $html, 1) ?? $html;
}

$title = registro_param('titulo') ?: 'CURSO INICIACION 4 EDICION';
$date = registro_param('fecha') ?: '2026-05-16';
$turn = registro_param('turno') ?: 'Tarde';

$html = file_get_contents(__DIR__ . '/registro.html');

if ($html === false) {
    http_response_code(500);
    echo 'No se ha podido cargar el formulario de registro.';
    exit;
}

$html = registro_replace_between_id($html, 'registroEventoTitulo', 'INSCRIPCION ' . $title);
$html = registro_replace_between_id($html, 'registroEventoFecha', registro_format_date($date));
$html = registro_replace_between_id($html, 'registroEventoTurno', registro_format_turn($turn));
$html = preg_replace('/<title>.*?<\/title>/s', '<title>Inscripcion - ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>', $html, 1) ?? $html;

echo $html;
