<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
$app = require dirname(__DIR__) . '/src/bootstrap.php';
$cfg = $app->config;
if ($cfg['app']['environment'] !== 'test' || !in_array($cfg['database']['host'], ['127.0.0.1','localhost'], true) || !str_starts_with($cfg['database']['name'], 'afec_test_')) { exit(2); }
$db = $app->database->connection();
switch ($argv[1] ?? '') {
    case 'stats':
        $result = [];
        foreach (['facility_recipes','facility_recipe_materials','installations','requirements','requirement_revisions','audit_log','operations','movements'] as $table) { $result[$table] = (int)$db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn(); }
        echo json_encode($result); break;
    case 'fail-audit':
        $db->exec("CREATE TRIGGER afec_test_fail_audit BEFORE INSERT ON audit_log FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Deliberate local test failure'"); break;
    case 'clear-failure': $db->exec('DROP TRIGGER IF EXISTS afec_test_fail_audit'); break;
    default: exit(3);
}
