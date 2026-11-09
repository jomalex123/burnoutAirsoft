<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/registration_documents.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function registration_document_check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = null;
try {
    $config = burnout_database_config();
    $environment = getenv('BURNOUT_ENV') ?: (burnout_env_config()['default'] ?? 'local');
    if ($environment !== 'local' || !in_array($config['host'], ['127.0.0.1', 'localhost'], true) || (int) $config['port'] !== 3306) {
        throw new RuntimeException('Solo se admite XAMPP local.');
    }
    registration_document_check(burnout_normalize_document("12345678 z\t-\n") === '12345678Z', 'Normalización incorrecta.');
    try {
        burnout_registration_documents(['12345678z', '12345678-Z']);
        throw new RuntimeException('No se detectó la repetición en el formulario.');
    } catch (BurnoutUserException $expected) {
        // Expected: same person inside one submission.
    }
    $pdo = burnout_pdo();
    $pdo->beginTransaction();
    $event = $pdo->prepare("INSERT INTO events (event_date, title, time_slot) VALUES ('2099-01-01', 'Prueba de documentos', 'M')");
    $event->execute();
    $firstEvent = (int) $pdo->lastInsertId();
    $event->execute();
    $secondEvent = (int) $pdo->lastInsertId();
    $registration = $pdo->prepare("INSERT INTO registrations (event_id, email, phone, accepted_rules) VALUES (?, 'test@example.invalid', '600000000', 1)");
    $ids = [];
    foreach ([$firstEvent, $firstEvent, $secondEvent] as $eventId) {
        $registration->execute([$eventId]);
        $ids[] = (int) $pdo->lastInsertId();
    }
    $insert = $pdo->prepare('INSERT INTO registration_attendees (registration_id, event_id, full_name, document) VALUES (?, ?, ?, ?)');
    $insert->execute([$ids[0], $firstEvent, 'Asistente ficticio', '12345678z']);
    foreach ([$ids[0], $ids[1]] as $registrationId) {
        foreach (['12345678-Z', "12345678 Z\t"] as $document) {
            try {
                $insert->execute([$registrationId, $firstEvent, 'Asistente ficticio', $document]);
                throw new RuntimeException('La base admitió un duplicado.');
            } catch (PDOException $expected) {
                registration_document_check((int) ($expected->errorInfo[1] ?? 0) === 1062, 'Error SQL distinto al de unicidad.');
            }
        }
    }
    try {
        burnout_assert_documents_available($pdo, $firstEvent, ['12345678Z']);
        throw new RuntimeException('El servidor no detectó la inscripción previa.');
    } catch (BurnoutUserException $expected) {
    }
    burnout_assert_documents_available($pdo, $secondEvent, ['12345678Z']);
    $insert->execute([$ids[2], $secondEvent, 'Asistente ficticio', '12345678-Z']);
    try {
        $insert->execute([$ids[0], $secondEvent, 'Asistente ficticio', '87654321X']);
        throw new RuntimeException('Se admitió un evento distinto al de la inscripción.');
    } catch (PDOException $expected) {
        registration_document_check((int) ($expected->errorInfo[1] ?? 0) === 1452, 'Error SQL distinto al de integridad referencial.');
    }
    $pdo->rollBack();
    echo "OK: normalización, repetición interna, inscripciones distintas, unicidad SQL, otras partidas e integridad evento/inscripción. Datos ficticios revertidos.\n";
} catch (Throwable $exception) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($exception->getMessage());
    fwrite(STDERR, "Fallo en comprobación de documentos.\n");
    exit(1);
}
