<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { exit(1); }
$app=require dirname(__DIR__).'/src/bootstrap.php'; $cfg=$app->config;
if($cfg['app']['environment']!=='test' || !in_array($cfg['database']['host'],['localhost','127.0.0.1'],true) || !str_starts_with($cfg['database']['name'],'afec_test_')) { exit(2); }
$path=$argv[1]; $job=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
$user=(new Afec\Repositories\UserRepository($app->database))->findById($job['actor_id']);
$service=new Afec\Services\MarketService($app->database);
$preview=$service->preview($job['stock'],null,null);
file_put_contents($path.'.ready','ready'); $deadline=microtime(true)+8;
while(!is_file($job['barrier'])) { if(microtime(true)>$deadline) { throw new RuntimeException('Market barrier timeout'); } usleep(10000); }
$result=$service->import(Afec\Repositories\UserRepository::publicUser($user),(int)$user['auth_version'],$job['key'],$job['stock'],null,null,'concurrency.csv',null,$preview['review_hash']);
echo json_encode($result,JSON_THROW_ON_ERROR);
