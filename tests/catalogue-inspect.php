<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
$app = require dirname(__DIR__) . '/src/bootstrap.php'; $cfg = $app->config;
if ($cfg['app']['environment'] !== 'test' || !in_array($cfg['database']['host'], ['127.0.0.1','localhost'], true) || !str_starts_with($cfg['database']['name'], 'afec_test_')) { exit(2); }
$db = $app->database->connection();
switch ($argv[1] ?? '') {
    case 'ambiguous-add': $db->exec("INSERT INTO commodities (code,name_es,name_en) VALUES ('test_water_alias','Agua','Water')"); exit;
    case 'ambiguous-clear': $db->exec("DELETE FROM commodities WHERE code='test_water_alias'"); exit;
    case 'inactive-water': $db->exec("UPDATE commodities SET active=0 WHERE code='water'"); exit;
    case 'active-water': $db->exec("UPDATE commodities SET active=1 WHERE code='water'"); exit;
}
if (($argv[1] ?? '') !== 'stats') { exit(3); }
$result = [];
foreach (['commodities','facility_recipes','facility_recipe_materials','operations'] as $table) { $result[$table] = (int)$db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn(); }
foreach (['original_commodity' => 'SELECT * FROM commodities WHERE id <= 4 ORDER BY id', 'movements' => 'SELECT * FROM movements ORDER BY id', 'requirements' => 'SELECT * FROM requirements ORDER BY installation_id, commodity_id'] as $key => $sql) { $result[$key . '_hash'] = hash('sha256', json_encode($db->query($sql)->fetchAll(), JSON_THROW_ON_ERROR)); }
echo json_encode($result, JSON_THROW_ON_ERROR);
