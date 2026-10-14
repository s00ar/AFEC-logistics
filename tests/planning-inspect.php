<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
$app = require dirname(__DIR__) . '/src/bootstrap.php';
$config = $app->config;
if ($config['app']['environment'] !== 'test' || !in_array($config['database']['host'], ['127.0.0.1', 'localhost'], true) || !str_starts_with($config['database']['name'], 'afec_test_')) { throw new RuntimeException('Solo bases locales de pruebas.'); }
$db = $app->database->connection();
$action = $argv[1] ?? 'stats';
if ($action === 'failure-on') {
    $db->exec("CREATE TRIGGER afec_test_fail_revision BEFORE INSERT ON requirement_revisions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Synthetic revision failure'");
    echo "Test trigger enabled.\n";
} elseif ($action === 'failure-off') {
    $db->exec('DROP TRIGGER IF EXISTS afec_test_fail_revision');
    echo "Test trigger removed.\n";
} elseif ($action === 'pages') {
    $id = $argv[2] ?? '';
    if (!preg_match('/^[1-9][0-9]{0,18}$/D', $id)) { throw new RuntimeException('Test system missing.'); }
    $system = $db->prepare('INSERT INTO systems (name) VALUES (?)');
    $installation = $db->prepare('INSERT INTO installations (system_id, name) VALUES (?, ?)');
    for ($i = 0; $i < 30; $i++) { $system->execute(['Pagination fixture ' . $i]); $installation->execute([$id, 'Installation pagination ' . $i]); }
    echo "Pagination fixtures added.\n";
} elseif ($action === 'stats') {
    $result = [];
    foreach (['systems', 'installations', 'requirements', 'operations', 'audit_log', 'requirement_revisions', 'movements'] as $table) {
        $result[$table] = (int)$db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }
    $result['movement_hash'] = hash('sha256', json_encode($db->query('SELECT * FROM movements ORDER BY id')->fetchAll(), JSON_THROW_ON_ERROR));
    echo json_encode($result, JSON_THROW_ON_ERROR);
} else { throw new RuntimeException('Unknown test action.'); }
