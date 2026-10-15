<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { exit(1); }
$app=require dirname(__DIR__).'/src/bootstrap.php'; $cfg=$app->config;
if($cfg['app']['environment']!=='test' || !in_array($cfg['database']['host'],['localhost','127.0.0.1'],true) || !str_starts_with($cfg['database']['name'],'afec_test_')) { exit(2); }
$db=$app->database->connection();
if(($argv[1]??'')==='stats') {
    $data=[];
    foreach(['market_stations','market_products','market_stock','market_observations','market_imports'] as $table) { $data[$table]=(int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn(); }
    foreach(['systems','installations','commanders','carriers','requirements','requirement_revisions','movements','commodities','commodity_sources','facility_recipes','facility_recipe_materials','wishlist_entries'] as $table) { $data[$table.'_hash']=hash('sha256',json_encode($db->query('SELECT * FROM '.$table)->fetchAll(),JSON_THROW_ON_ERROR)); }
    echo json_encode($data,JSON_THROW_ON_ERROR); exit;
}
if(($argv[1]??'')==='schema-off') { $db->exec("UPDATE schema_migrations SET status='running' WHERE version='009_system_markets.sql'"); exit; }
if(($argv[1]??'')==='schema-on') { $db->exec("UPDATE schema_migrations SET status='applied' WHERE version='009_system_markets.sql'"); exit; }
exit(3);
