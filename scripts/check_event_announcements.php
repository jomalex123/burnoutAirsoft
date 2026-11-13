<?php

declare(strict_types=1);

// Dobles de transporte, autenticación y PDO: no conecta a MySQL ni envía correos.
require_once __DIR__ . '/../config/errors.php';
ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');

$testAdmin = true;
$testEnabled = true;
$testEmails = ['principal@example.test', 'otro@example.test'];
$testSent = [];

function burnout_current_admin(): ?array { return $GLOBALS['testAdmin'] ? ['id' => 1] : null; }
function burnout_check_csrf(?string $token): bool { return $token === 'test'; }
function burnout_mail_config(): array { return ['enabled' => $GLOBALS['testEnabled']]; }
function burnout_send_plain_mail(string $to, string $subject, string $body): void {
    $GLOBALS['testSent'][] = [$to, $subject, $body];
    if ($to === 'fail@example.test') { throw new RuntimeException('Fallo ficticio'); }
}
class BurnoutAnnouncementTestStatement {
    public function execute(array $params): void {}
    public function fetch(): array { return ['title' => 'Evento ficticio', 'event_date' => '2026-11-20', 'time_slot' => 'M']; }
    public function fetchAll(int $mode): array { return $GLOBALS['testEmails']; }
}
class BurnoutAnnouncementTestPdo {
    public function beginTransaction(): void {}
    public function commit(): void {}
    public function rollBack(): void {}
    public function prepare(string $sql): BurnoutAnnouncementTestStatement { return new BurnoutAnnouncementTestStatement(); }
}
function burnout_pdo(): BurnoutAnnouncementTestPdo { return new BurnoutAnnouncementTestPdo(); }

$source = file_get_contents(__DIR__ . '/../admin_event_announcement.php');
$source = preg_replace('/^require_once .*;\R/m', '', $source);
$source = substr($source, strlen('<?php'));
$_SESSION = [];
$_SERVER['REQUEST_METHOD'] = 'POST';

function burnout_test_announcement(array $post): array {
    $_POST = $post + ['csrf_token' => 'test'];
    http_response_code(200);
    ob_start();
    eval($GLOBALS['source']);
    return json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
}
function burnout_assert_announcement(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
}
$start = ['action' => 'start', 'event_id' => '1', 'subject' => 'Información', 'body' => 'Mensaje de prueba'];
$testAdmin = false;
burnout_assert_announcement(!burnout_test_announcement($start)['ok'] && http_response_code() === 401, 'Autenticación');
$testAdmin = true;
burnout_assert_announcement(!burnout_test_announcement($start + ['csrf_token' => ['test']])['ok'] && http_response_code() === 403, 'CSRF con array');
burnout_assert_announcement(!burnout_test_announcement(array_replace($start, ['event_id' => ['1']]))['ok'], 'ID con array');
burnout_assert_announcement(!burnout_test_announcement(array_replace($start, ['subject' => "Asunto\r\nBcc: x"]))['ok'], 'Inyección de cabeceras');
$testEnabled = false;
burnout_assert_announcement(!burnout_test_announcement($start)['ok'], 'Correo desactivado');
$testEnabled = true;
$testEmails = [];
burnout_assert_announcement(!burnout_test_announcement($start)['ok'], 'Sin inscripciones');
$testEmails = ['principal@example.test', 'fail@example.test'];
$run = burnout_test_announcement($start)['run'];
burnout_assert_announcement($run['total'] === 2 && count($testSent) === 0, 'Iniciar no envía');
burnout_assert_announcement(!burnout_test_announcement($start)['ok'], 'Un único envío pendiente');
$next = ['action' => 'next', 'token' => $run['token'], 'index' => '0'];
burnout_assert_announcement(!burnout_test_announcement(array_replace($next, ['token' => 'otro']))['ok'], 'Token incorrecto');
burnout_assert_announcement(!burnout_test_announcement(array_replace($next, ['index' => '1']))['ok'], 'No saltar destinatarios');
$run = burnout_test_announcement($next)['run'];
burnout_assert_announcement($run['sent'] === 1 && count($testSent) === 1, 'Primer contacto');
burnout_test_announcement($next);
burnout_assert_announcement(count($testSent) === 1, 'No repetir paso procesado');
$run = burnout_test_announcement(array_replace($next, ['index' => '1']))['run'];
burnout_assert_announcement($run['done'] && $run['failed'] === 1 && $run['sent'] === 1, 'Resultado parcial');
burnout_test_announcement(array_replace($next, ['index' => '1']));
burnout_assert_announcement(count($testSent) === 2, 'No repetir último paso');
$status = burnout_test_announcement(['action' => 'status']);
burnout_assert_announcement($status['run']['done'] && !isset($status['run']['recipients']), 'Estado sin direcciones');
echo "OK: comunicados, permisos, CSRF, validación, fallos y repetición de pasos (sin correo real).\n";
