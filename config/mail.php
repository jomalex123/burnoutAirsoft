<?php

declare(strict_types=1);

require_once __DIR__ . '/env_loader.php';

function burnout_mail_config(): array
{
    $config = burnout_env_config();
    $environment = getenv('BURNOUT_ENV') ?: ($config['default'] ?? 'local');
    $mailConfig = $config['mail'][$environment] ?? $config['mail']['default'] ?? [];

    return $mailConfig + [
        'enabled' => true,
        'host' => null,
        'port' => 587,
        'encryption' => 'tls',
        'username' => null,
        'password' => null,
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

    if (!empty($config['host']) && !empty($config['username']) && !empty($config['password'])) {
        burnout_send_smtp_mail($config, $to, $subject, $body);
        return;
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

function burnout_send_smtp_mail(array $config, string $to, string $subject, string $body): void
{
    $fromEmail = (string) $config['from_email'];
    $fromName = (string) ($config['from_name'] ?? '');
    $replyTo = (string) ($config['reply_to'] ?? '');
    $host = (string) $config['host'];
    $port = (int) ($config['port'] ?? 587);
    $encryption = strtolower((string) ($config['encryption'] ?? 'tls'));
    $username = (string) $config['username'];
    $password = (string) $config['password'];
    $transportHost = $encryption === 'ssl' || $encryption === 'smtps' ? 'ssl://' . $host : $host;
    $socket = @stream_socket_client(
        sprintf('%s:%d', $transportHost, $port),
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT
    );

    if (!is_resource($socket)) {
        throw new RuntimeException(sprintf('No se ha podido conectar con el servidor SMTP: %s', $errstr ?: (string) $errno));
    }

    stream_set_timeout($socket, 20);

    try {
        burnout_smtp_expect($socket, [220]);
        burnout_smtp_command($socket, 'EHLO ' . burnout_smtp_hostname(), [250]);

        if ($encryption === 'tls' || $encryption === 'starttls') {
            burnout_smtp_command($socket, 'STARTTLS', [220]);

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('No se ha podido activar TLS en la conexion SMTP.');
            }

            burnout_smtp_command($socket, 'EHLO ' . burnout_smtp_hostname(), [250]);
        }

        burnout_smtp_command($socket, 'AUTH LOGIN', [334]);
        burnout_smtp_command($socket, base64_encode($username), [334]);
        burnout_smtp_command($socket, base64_encode($password), [235]);
        burnout_smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        burnout_smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        burnout_smtp_command($socket, 'DATA', [354]);
        burnout_smtp_write($socket, burnout_smtp_message($to, $subject, $body, $fromEmail, $fromName, $replyTo));
        burnout_smtp_expect($socket, [250]);
        burnout_smtp_command($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }
}

function burnout_smtp_message(string $to, string $subject, string $body, string $fromEmail, string $fromName, string $replyTo): string
{
    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'To: ' . $to,
        'From: ' . burnout_mailbox_header($fromEmail, $fromName),
        'Subject: =?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], '', $subject)) . '?=',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];

    if ($replyTo !== '') {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    $message = str_replace(["\r\n", "\r"], "\n", $body);
    $message = preg_replace('/^\./m', '..', $message) ?? $message;
    $message = str_replace("\n", "\r\n", $message);

    return implode("\r\n", $headers) . "\r\n\r\n" . $message . "\r\n.\r\n";
}

function burnout_smtp_hostname(): string
{
    $host = $_SERVER['SERVER_NAME'] ?? gethostname() ?: 'localhost';

    return preg_replace('/[^A-Za-z0-9.-]/', '', $host) ?: 'localhost';
}

function burnout_smtp_command($socket, string $command, array $expectedCodes): string
{
    burnout_smtp_write($socket, $command . "\r\n");

    return burnout_smtp_expect($socket, $expectedCodes);
}

function burnout_smtp_write($socket, string $data): void
{
    if (fwrite($socket, $data) === false) {
        throw new RuntimeException('No se ha podido escribir en la conexion SMTP.');
    }
}

function burnout_smtp_expect($socket, array $expectedCodes): string
{
    $response = burnout_smtp_read_response($socket);
    $code = (int) substr($response, 0, 3);

    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('Respuesta SMTP inesperada: ' . trim($response));
    }

    return $response;
}

function burnout_smtp_read_response($socket): string
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (preg_match('/^\d{3}\s/', $line)) {
            return $response;
        }
    }

    $meta = stream_get_meta_data($socket);

    if (!empty($meta['timed_out'])) {
        throw new RuntimeException('Tiempo de espera agotado esperando respuesta SMTP.');
    }

    throw new RuntimeException('No se ha recibido respuesta SMTP.');
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
