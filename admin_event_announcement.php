<?php

declare(strict_types=1);

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/mail.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        http_response_code(405);
        throw new BurnoutUserException('Método no permitido.');
    }
    if (!burnout_current_admin()) {
        http_response_code(401);
        throw new BurnoutUserException('Inicia sesión en administración.');
    }
    if (!is_string($_POST['csrf_token'] ?? null) || !burnout_check_csrf($_POST['csrf_token'])) {
        http_response_code(403);
        throw new BurnoutUserException('Sesión caducada. Recarga la página.');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'start') {
        if (!empty($_SESSION['event_announcement']) && !$_SESSION['event_announcement']['done']) {
            throw new BurnoutUserException('Ya hay un comunicado en curso. Recarga la página para continuar.');
        }
        $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $subject = $_POST['subject'] ?? null;
        $body = $_POST['body'] ?? null;
        if (!$eventId || !is_string($subject) || !is_string($body)) {
            throw new BurnoutUserException('Completa el evento, asunto y mensaje.');
        }
        $subject = trim($subject);
        $body = trim($body);
        if (preg_match('/^.{1,200}$/us', $subject) !== 1 || preg_match('/[\r\n\x00-\x1f\x7f]/', $subject)
            || preg_match('/^.{1,20000}$/us', $body) !== 1 || strpos($body, "\0") !== false) {
            throw new BurnoutUserException('Introduce un asunto válido (máximo 200 caracteres) y un mensaje (máximo 20000 caracteres).');
        }
        if (empty(burnout_mail_config()['enabled'])) {
            throw new BurnoutUserException('El envío de correo está desactivado en este entorno.');
        }
        $pdo = burnout_pdo();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare('SELECT title, event_date, time_slot FROM events WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $eventId]);
            $event = $statement->fetch();
            if (!$event) {
                throw new BurnoutUserException('El evento seleccionado no existe.');
            }
            $statement = $pdo->prepare('SELECT email FROM registrations WHERE event_id = :id ORDER BY id');
            $statement->execute(['id' => $eventId]);
            $recipients = $statement->fetchAll(PDO::FETCH_COLUMN);
            if (!$recipients) {
                throw new BurnoutUserException('Este evento no tiene inscripciones.');
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
        $turn = ['M' => 'Mañana', 'T' => 'Tarde', 'N' => 'Noche'][$event['time_slot']] ?? '';
        $_SESSION['event_announcement'] = [
            'token' => bin2hex(random_bytes(24)),
            'recipients' => $recipients,
            'subject' => $subject,
            'body' => $event['title'] . ' · ' . $event['event_date'] . ' · ' . $turn . "\n\n" . $body . "\n\nBurnout Airsoft",
            'processed' => 0, 'sent' => 0, 'failed' => 0, 'done' => false,
        ];
    } elseif ($action === 'next') {
        $token = $_POST['token'] ?? null;
        if (!is_string($token) || !isset($_SESSION['event_announcement'])
            || !hash_equals($_SESSION['event_announcement']['token'], $token)) {
            throw new BurnoutUserException('El comunicado ha caducado. Recarga la página.');
        }
        $run = &$_SESSION['event_announcement'];
        $index = filter_var($_POST['index'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($index === false || $index === null || $index > $run['processed']) {
            throw new BurnoutUserException('Paso de envío no válido.');
        }
        // El bloqueo de sesión serializa los pasos; repetir un índice ya procesado no reenvía.
        if (!$run['done'] && $index === $run['processed']) {
            if (empty(burnout_mail_config()['enabled'])) {
                throw new BurnoutUserException('El envío de correo está desactivado.');
            }
            try {
                burnout_send_plain_mail((string) $run['recipients'][$index], $run['subject'], $run['body']);
                $run['sent']++;
            } catch (Throwable $exception) {
                error_log('Fallo al enviar comunicado de evento: ' . $exception->getMessage());
                $run['failed']++;
            }
            $run['processed']++;
            $run['done'] = $run['processed'] >= count($run['recipients']);
        }
    } elseif ($action !== 'status') {
        throw new BurnoutUserException('Acción no válida.');
    }
    $run = $_SESSION['event_announcement'] ?? null;
    echo json_encode(['ok' => true, 'run' => $run ? [
        'token' => $run['token'], 'total' => count($run['recipients']),
        'processed' => $run['processed'], 'sent' => $run['sent'], 'failed' => $run['failed'], 'done' => $run['done'],
        'subject' => $run['subject'], 'body' => $run['body'],
    ] : null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    if (http_response_code() < 400) {
        http_response_code($exception instanceof BurnoutUserException ? 400 : 500);
    }
    echo json_encode(['ok' => false, 'message' => burnout_error_message($exception, 'enviar el comunicado')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
