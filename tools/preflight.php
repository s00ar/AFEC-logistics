<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$app=require dirname(__DIR__).'/src/bootstrap.php';
$report=['php'=>PHP_VERSION,'pdo_mysql'=>extension_loaded('pdo_mysql'),'mbstring'=>extension_loaded('mbstring'),'database_connected'=>false];
try {
    $db=$app->database->connection(); $report['database_connected']=true;
    $report['database_version']=$db->query('SELECT VERSION()')->fetchColumn();
    $report['table_count']=count($db->query('SHOW TABLES')->fetchAll());
} catch (Throwable) { $report['error']='Revisar host, configuración privada, extensiones y permisos.'; }
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($report['database_connected']&&$report['pdo_mysql']&&$report['mbstring'] ? 0 : 1);
