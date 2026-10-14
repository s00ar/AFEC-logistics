<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$app = require dirname(__DIR__) . '/src/bootstrap.php';
$cfg = $app->config;
if ($cfg['app']['environment'] !== 'test' || !in_array($cfg['database']['host'], ['127.0.0.1','localhost'], true) || !str_starts_with($cfg['database']['name'], 'afec_test_')) { throw new RuntimeException('Las pruebas requieren una base afec_test_* local.'); }
$db = $app->database->connection();
$count = 0;
function check(bool $value, string $name): void { global $count; if (!$value) { throw new RuntimeException($name); } $count++; }
function reject(callable $action, string $name): void { $rejected = false; try { $action(); } catch (PDOException) { $rejected = true; } check($rejected, $name); }
$tables = $db->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()")->fetchAll();
check(count($tables) === 18, 'Versioned schema has 18 tables, including accounts lock, lists and sources');
check(count(array_filter($tables, static fn(array $row):bool => $row['ENGINE'] !== 'InnoDB')) === 0, 'Every table uses InnoDB');
$db->beginTransaction();
try {
    reject(fn() => $db->exec("INSERT INTO requirements (installation_id,commodity_id,required_quantity) VALUES (999999,1,100)"), 'Foreign keys reject nonexistent installation');
    reject(fn() => $db->exec("INSERT INTO requirements (installation_id,commodity_id,required_quantity) VALUES (1,1,100)"), 'Requirement pair is unique');
    reject(fn() => $db->exec("UPDATE requirements SET required_quantity=-1 WHERE installation_id=1 AND commodity_id=1"), 'Unsigned objective rejects negative quantity');
    reject(fn() => $db->exec("UPDATE users SET role='invented-role' WHERE id=1"), 'Database enforces role list');
    reject(fn() => $db->exec('DELETE FROM installations WHERE id=1'), 'Referenced installation cannot be deleted');
    $operation = $db->prepare("INSERT INTO operations (actor_user_id,scope,idempotency_key,request_hash) VALUES (1,'test.schema',?,?)");
    $operation->execute(['same-key', hash('sha256','same-key',true)]);
    $op = $db->lastInsertId();
    reject(fn() => $operation->execute(['same-key', hash('sha256','other-payload',true)]), 'Database rejects duplicate operation key even for a different hash');
    $movement = $db->prepare("INSERT INTO movements (installation_id,commodity_id,created_by_user_id,commander_id,delta_quantity,movement_type,operation_id,occurred_at) VALUES (1,1,1,?,?,'delivery',?,UTC_TIMESTAMP(6))");
    reject(fn() => $movement->execute([null,10,$op]), 'New delivery requires a commander');
    reject(fn() => $movement->execute([1,0,$op]), 'Movement quantity cannot be zero');
    $movement->execute([2,10,$op]);
    $created = $db->lastInsertId();
    $pair = $db->query('SELECT commander_id,created_by_user_id FROM movements WHERE id=' . (int) $created)->fetch();
    check((int)$pair['commander_id'] === 2 && (int)$pair['created_by_user_id'] === 1, 'Commander and actor are separate identities');
} finally { $db->rollBack(); }
$query = $db->prepare("SELECT COUNT(*) FROM operations WHERE scope='test.schema'"); $query->execute();
check((int)$query->fetchColumn() === 0, 'Transaction rollback removes test operation and movement together');
echo "Database checks passed: $count\n";
