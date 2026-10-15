<?php
declare(strict_types=1);

// Deterministic expiry checks: only CLI, isolated local test configuration and existing sessions.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$app = require dirname(__DIR__) . '/src/bootstrap.php';
$cfg = $app->config;
if ($cfg['app']['environment'] !== 'test' || !in_array($cfg['database']['host'], ['127.0.0.1', 'localhost'], true) || !str_starts_with($cfg['database']['name'], 'afec_test_')) {
    throw new RuntimeException('La sesión de prueba requiere una base afec_test_* local y entorno test.');
}
$id = $argv[1] ?? '';
$action = $argv[2] ?? '';
if (!preg_match('/^[a-zA-Z0-9,-]{16,128}$/D', $id) || !is_file($cfg['storage_path'] . '/sessions/sess_' . $id) || !in_array($action, ['inspect', 'idle', 'absolute', 'remember', 'legacy'], true)) {
    throw new RuntimeException('Sesión o acción de prueba no válida.');
}
session_id($id);
$app->session();
if (!isset($_SESSION['user_id'])) { throw new RuntimeException('La sesión de prueba debe estar autenticada.'); }
if ($action === 'idle' || $action === 'legacy') {
    $_SESSION['last_seen'] = time() - $cfg['session']['idle_seconds'];
    if ($action === 'legacy') { unset($_SESSION['remember_me']); }
} elseif ($action === 'absolute' || $action === 'remember') {
    $key = $action === 'absolute' ? 'absolute_seconds' : 'remember_seconds';
    $_SESSION['authenticated_at'] = time() - $cfg['session'][$key];
}
echo json_encode(['gc_maxlifetime' => (int) ini_get('session.gc_maxlifetime'), 'remember_me' => $_SESSION['remember_me'] ?? false]);
session_write_close();
