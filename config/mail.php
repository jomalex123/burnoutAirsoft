<?php

declare(strict_types=1);

function burnout_mail_config(): array
{
    $configFile = __DIR__ . '/env.php';
    $config = is_file($configFile) ? require $configFile : [];
    $environment = getenv('BURNOUT_ENV') ?: ($config['default'] ?? 'local');
    $mailConfig = $config['mail'][$environment] ?? $config['mail']['default'] ?? [];

    return $mailConfig + [
        'enabled' => true,
        'from_email' => 'no-reply@burnoutairsoft.com',
        'from_name' => 'Burnout Airsoft',
        'reply_to' => null,
    ];
}

function burnout_send_plain_mail(string $to, string $subject, string $body): void
{
    $config = burnout_mail_config();

    if (empty($config['enabled'])) {
        return;
    }

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('El correo de destino no es valido.');
    }

    $fromEmail = (string) ($config['from_email'] ?? '');

    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Configura un from_email valido para poder enviar correos.');
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . burnout_mailbox_header($fromEmail, (string) ($config['from_name'] ?? '')),
    ];

    $replyTo = (string) ($config['reply_to'] ?? '');

    if ($replyTo !== '') {
        if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Configura un reply_to valido para poder enviar correos.');
        }

        $headers[] = 'Reply-To: ' . $replyTo;
    }

    $message = str_replace(["\r\n", "\r"], "\n", $body);
    $message = str_replace("\n", "\r\n", $message);
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    if (!mail($to, $encodedSubject, $message, implode("\r\n", $headers))) {
        throw new RuntimeException('No se ha podido enviar el correo de confirmacion.');
    }
}

function burnout_mailbox_header(string $email, string $name): string
{
    $safeEmail = str_replace(["\r", "\n"], '', $email);
    $safeName = trim(str_replace(["\r", "\n"], '', $name));

    if ($safeName === '') {
        return $safeEmail;
    }

    return '=?UTF-8?B?' . base64_encode($safeName) . '?= <' . $safeEmail . '>';
}
