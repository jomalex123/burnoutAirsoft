<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/hq.php';
$config = burnout_database_config();
$environment = getenv('BURNOUT_ENV') ?: (burnout_env_config()['default'] ?? 'local');
if ($environment !== 'local' || !in_array($config['host'], ['127.0.0.1', 'localhost'], true)) exit("Solo se permite en local.\n");
$pdo = burnout_pdo();
try {
    $pdo->beginTransaction();
    $rows = $pdo->query("SELECT * FROM hq_missions WHERE status = 'active' FOR UPDATE")->fetchAll();
    $backup = tempnam(sys_get_temp_dir(), 'burnout-hq-before-pending-');
    if ($backup === false || file_put_contents($backup, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) === false) throw new RuntimeException('No se ha podido guardar la copia.');
    $count = $pdo->exec("UPDATE hq_missions SET status = 'draft', acknowledged_at = NULL WHERE status = 'active'");
    $pdo->commit();
    echo "Copia local: " . $backup . PHP_EOL . "Misiones pendientes: " . $count . PHP_EOL;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log((string) $exception);
    fwrite(STDERR, "No se ha podido reiniciar el guion.\n");
    exit(1);
}
