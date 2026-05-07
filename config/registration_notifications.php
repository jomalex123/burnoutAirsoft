<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/mail.php';

function burnout_registration_notifications_table(): void
{
    static $ready = false;

    if ($ready) {
        return;
    }

    burnout_pdo()->exec(
        'CREATE TABLE IF NOT EXISTS registration_email_notifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            registration_id BIGINT UNSIGNED NOT NULL,
            recipient_email VARCHAR(190) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            body MEDIUMTEXT NOT NULL,
            status ENUM("pending", "sent", "failed") NOT NULL DEFAULT "pending",
            error_message TEXT DEFAULT NULL,
            sent_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY registration_email_notifications_registration_id_index (registration_id),
            KEY registration_email_notifications_status_index (status),
            CONSTRAINT registration_email_notifications_registration_id_foreign
                FOREIGN KEY (registration_id) REFERENCES registrations (id)
                ON UPDATE CASCADE
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $ready = true;
}

function burnout_registration_notification_create(int $registrationId, string $recipientEmail, string $subject, string $body): int
{
    burnout_registration_notifications_table();

    $statement = burnout_pdo()->prepare(
        'INSERT INTO registration_email_notifications (registration_id, recipient_email, subject, body, status)
         VALUES (:registration_id, :recipient_email, :subject, :body, "pending")'
    );
    $statement->execute([
        'registration_id' => $registrationId,
        'recipient_email' => $recipientEmail,
        'subject' => $subject,
        'body' => $body,
    ]);

    return (int) burnout_pdo()->lastInsertId();
}

function burnout_registration_notification_find(int $notificationId): ?array
{
    burnout_registration_notifications_table();

    $statement = burnout_pdo()->prepare(
        'SELECT id, registration_id, recipient_email, subject, body, status, error_message, sent_at, created_at, updated_at
         FROM registration_email_notifications
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $notificationId]);
    $notification = $statement->fetch();

    return $notification ?: null;
}

function burnout_registration_notification_latest(int $registrationId): ?array
{
    burnout_registration_notifications_table();

    $statement = burnout_pdo()->prepare(
        'SELECT id, registration_id, recipient_email, subject, body, status, error_message, sent_at, created_at, updated_at
         FROM registration_email_notifications
         WHERE registration_id = :registration_id
         ORDER BY id DESC
         LIMIT 1'
    );
    $statement->execute(['registration_id' => $registrationId]);
    $notification = $statement->fetch();

    return $notification ?: null;
}

function burnout_registration_notification_mark_sent(int $notificationId): void
{
    $statement = burnout_pdo()->prepare(
        'UPDATE registration_email_notifications
         SET status = "sent", error_message = NULL, sent_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );
    $statement->execute(['id' => $notificationId]);
}

function burnout_registration_notification_mark_failed(int $notificationId, string $errorMessage): void
{
    $statement = burnout_pdo()->prepare(
        'UPDATE registration_email_notifications
         SET status = "failed", error_message = :error_message
         WHERE id = :id'
    );
    $statement->execute([
        'id' => $notificationId,
        'error_message' => substr($errorMessage, 0, 2000),
    ]);
}

function burnout_registration_notification_send(int $notificationId): void
{
    $notification = burnout_registration_notification_find($notificationId);

    if (!$notification) {
        throw new RuntimeException('La notificación seleccionada no existe.');
    }

    try {
        $mailConfig = burnout_mail_config();

        if (empty($mailConfig['enabled'])) {
            throw new RuntimeException('El envío de correo está desactivado.');
        }

        burnout_send_plain_mail(
            (string) $notification['recipient_email'],
            (string) $notification['subject'],
            (string) $notification['body']
        );
        burnout_registration_notification_mark_sent($notificationId);
    } catch (Throwable $exception) {
        burnout_registration_notification_mark_failed($notificationId, $exception->getMessage());
        throw $exception;
    }
}

function burnout_registration_notification_resend_latest(int $registrationId): int
{
    $latest = burnout_registration_notification_latest($registrationId);

    if (!$latest) {
        throw new RuntimeException('No hay ninguna notificación previa para reenviar.');
    }

    $notificationId = burnout_registration_notification_create(
        $registrationId,
        (string) $latest['recipient_email'],
        (string) $latest['subject'],
        (string) $latest['body']
    );

    burnout_registration_notification_send($notificationId);

    return $notificationId;
}
