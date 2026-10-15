<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$app = require dirname(__DIR__) . '/src/bootstrap.php'; $cfg = $app->config;
if ($cfg['app']['environment'] !== 'test' || !in_array($cfg['database']['host'], ['127.0.0.1','localhost'], true) || !str_starts_with($cfg['database']['name'], 'afec_test_')) { throw new RuntimeException('Solo pruebas locales aisladas.'); }
$db = $app->database->connection(); $action = $argv[1] ?? '';
if ($action === 'reset-queue') {
    $directory = $cfg['storage_path'] . '/password-help';
    foreach (glob($directory . '/*') ?: [] as $path) {
        if (preg_match('/^[1-9][0-9]{0,18}\.(json|lock)$/D', basename($path))) { unlink($path); }
    }
    echo 'Test queue reset'; exit;
}
if ($action === 'clear-help-throttle') {
    $q = $db->prepare('DELETE FROM login_attempts WHERE network_hash=?');
    foreach (['127.0.0.1','127.0.0.2'] as $ip) { $q->execute([hash('sha256','password-help:network:' . $ip,true)]); }
    echo 'Test counters reset'; exit;
}
if ($action === 'stats') {
    $q = $db->prepare("SELECT auth_version,version FROM users WHERE id=?"); $q->execute([$argv[2]]); $row = $q->fetch();
    $q = $db->prepare("SELECT COUNT(*) FROM audit_log WHERE entity_type='user' AND entity_id=? AND action='password'"); $q->execute([$argv[2]]);
    echo json_encode($row + ['password_changes'=>(int)$q->fetchColumn()]); exit;
}
if ($action === 'request-worker') {
    $job = json_decode(file_get_contents($argv[2]), true, 8, JSON_THROW_ON_ERROR);
    file_put_contents($argv[2] . '.ready', 'ready'); $deadline = microtime(true) + 8;
    while (!is_file($job['barrier'])) { if (microtime(true) > $deadline) { throw new RuntimeException('Barrier timed out.'); } usleep(10000); }
    echo json_encode($app->accounts()->requestHelp(['username'=>$job['username']], '127.0.0.2')); exit;
}
throw new RuntimeException('Acción desconocida.');
