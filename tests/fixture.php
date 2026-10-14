<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
$app=require dirname(__DIR__).'/src/bootstrap.php';
$cfg=$app->config;
if ($cfg['app']['environment']!=='test'||!in_array($cfg['database']['host'],['127.0.0.1','localhost'],true)||!str_starts_with($cfg['database']['name'],'afec_test_')) { fwrite(STDERR,"Fixtures solo en bases afec_test_* locales y entorno test.\n"); exit(1); }
$db=$app->database->connection(); $action=$argv[1]??'seed';
if ($action==='reset') {
    $db->exec('DROP TRIGGER IF EXISTS afec_test_fail_revision');
    $db->exec('DROP TRIGGER IF EXISTS afec_test_fail_audit');
    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    try { foreach (['wishlist_entries','commodity_sources','audit_log','movements','requirement_revisions','operations','import_items','import_runs','requirements','installations','systems','commodities','users','commanders','carriers','login_attempts'] as $table) { $db->exec('TRUNCATE TABLE '.$table); } }
    finally { $db->exec('SET FOREIGN_KEY_CHECKS=1'); }
    echo "Local fixture reset.\n"; exit;
}
if (in_array($action,['deactivate_viewer','revoke_operator'],true)) {
    $db->exec($action==='deactivate_viewer' ? "UPDATE users SET active=0 WHERE username='viewer'" : "UPDATE users SET auth_version=auth_version+1 WHERE username='operator'"); echo "Fixture updated.\n"; exit;
}
if ($action!=='seed'||(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()!==1) { throw new RuntimeException('Crear primero un único administrador de pruebas.'); }
$password=getenv('AFEC_TEST_PASSWORD'); if (!$password) { throw new RuntimeException('Falta contraseña sintética de pruebas.'); }
$db->beginTransaction();
try {
    $db->exec("INSERT INTO commanders (display_name,normalized_name) VALUES ('Comandante Uno','comandante uno'),('Comandante Dos','comandante dos')");
    $db->exec("INSERT INTO carriers (code,name) VALUES ('TST-001','Carrier de pruebas')");
    $q=$db->prepare('INSERT INTO users (username,display_name,password_hash,role,commander_id) VALUES (?,?,?,?,?)');
    foreach ([['viewer','Lector','viewer',null],['operator','Operador','operator',1]] as [$username,$name,$role,$commander]) { $q->execute([$username,$name,password_hash($password,PASSWORD_DEFAULT),$role,$commander]); }
    $q=$db->prepare('INSERT INTO systems (name,description) VALUES (?,?)');
    foreach ([['Sistema <script>alert(1)</script>','Datos sintéticos para verificar escapado y totales'],['Segundo sistema',null],['Sin objetivos',null],['Saldo por revisar',null],['Cantidades grandes',null]] as $row) { $q->execute($row); }
    $db->exec("INSERT INTO installations (system_id,name,body_name) VALUES (1,'Puerto Aurora','A 1'),(1,'Centro de investigación','A 2'),(2,'Puerto secundario','B 1'),(3,'Proyecto nuevo','C 1'),(4,'Instalación con ajuste','D 1'),(5,'Prueba de precisión','E 1')");
    $db->exec("INSERT INTO installations (system_id,name,archived_at) VALUES (1,'Archivada',UTC_TIMESTAMP(6))");
    $db->exec("INSERT INTO commodities (code,name_es) VALUES ('water','Agua'),('steel','Acero'),('legacy','Material de prueba')");
    $db->exec("INSERT INTO commodities (code,name_es,active) VALUES ('inactive','Inactivo',0)");
    $db->exec('INSERT INTO requirements (installation_id,commodity_id,required_quantity) VALUES (1,1,100),(1,2,100),(2,1,100),(3,1,100),(5,1,10),(6,3,9007199254740993),(7,1,100000)');
    $op=$db->prepare("INSERT INTO operations (actor_user_id,scope,idempotency_key,request_hash) VALUES (1,'test.fixture',?,?)");
    $move=$db->prepare('INSERT INTO movements (installation_id,commodity_id,created_by_user_id,commander_id,carrier_id,delta_quantity,movement_type,operation_id,occurred_at,reason) VALUES (?,?,1,?,?,?,?,?,UTC_TIMESTAMP(6),?)');
    $rows=[[1,1,2,1,'200','delivery'],[2,1,1,null,'25','delivery'],[3,1,1,null,'10','delivery'],[5,1,null,null,'-3','adjustment'],[6,3,null,null,'9007199254740993','opening_balance'],[7,1,1,null,'100000','delivery']];
    foreach ($rows as $index=>[$installation,$commodity,$commander,$carrier,$quantity,$type]) {
        $key='fixture-'.$index; $op->execute([$key,hash('sha256',$key,true)]);
        $move->execute([$installation,$commodity,$commander,$carrier,$quantity,$type,$db->lastInsertId(),'Datos sintéticos; nunca importar en producción']);
    }
    $db->commit();
} catch (Throwable $error) { $db->rollBack(); throw $error; }
echo "Synthetic fixture seeded.\n";
