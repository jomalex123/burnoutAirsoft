<?php

declare(strict_types=1);

function burnout_normalize_document(string $document): string
{
    return strtoupper(str_replace([" ", "-", "\t", "\n", "\r", "\v", "\f"], '', $document));
}

function burnout_registration_documents(array $documents): array
{
    $normalized = [];
    foreach ($documents as $document) {
        if (!is_string($document) || strlen($document) > 100) {
            throw new BurnoutUserException('Introduce un documento válido para cada asistente.');
        }
        $value = burnout_normalize_document($document);
        if (in_array($value, $normalized, true)) {
            throw new BurnoutUserException('Hay documentos repetidos entre los asistentes. Cada persona solo puede aparecer una vez.');
        }
        $normalized[] = $value;
    }
    return $normalized;
}

// Invocar dentro de la transacción, después de bloquear la partida.
function burnout_assert_documents_available(PDO $pdo, int $eventId, array $documents): void
{
    $placeholders = implode(',', array_fill(0, count($documents), '?'));
    $statement = $pdo->prepare('SELECT id FROM registration_attendees WHERE event_id = ? AND document_key IN (' . $placeholders . ') LIMIT 1');
    $statement->execute(array_merge([$eventId], $documents));
    if ($statement->fetchColumn() !== false) {
        throw new BurnoutUserException('Uno o varios asistentes ya están inscritos en esta partida. Revisa los participantes antes de volver a enviar la inscripción.');
    }
}
