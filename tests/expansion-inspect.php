<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
$app=require dirname(__DIR__).'/src/bootstrap.php'; $cfg=$app->config;
if($cfg['app']['environment']!=='test'||!in_array($cfg['database']['host'],['127.0.0.1','localhost'],true)||!str_starts_with($cfg['database']['name'],'afec_test_')) { throw new RuntimeException('Solo base local de pruebas.'); }
$db=$app->database->connection(); $action=$argv[1]??'stats';
if($action==='password_operation') {
    $q=$db->prepare('SELECT request_hash,secret_hash,result_json FROM operations WHERE scope=? AND idempotency_key=?'); $q->execute(['admin.user.create',$argv[2]??'']); $row=$q->fetch();
    $password=getenv('AFEC_TEST_PASSWORD');
    $legacy=['username'=>'crew_test','display_name'=>'Comandante de prueba','role'=>'viewer','password'=>$password,'reason'=>'Alta de prueba']; ksort($legacy);
    echo json_encode(['slow_verifier_valid'=>password_verify($password,$row['secret_hash']), 'uses_plaintext_digest'=>hash_equals($row['request_hash'],hash('sha256',json_encode($legacy,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),true)), 'receipt_contains_password'=>str_contains($row['result_json'],$password)]); exit;
}
if($action==='fail_audit') {
    $db->exec("CREATE TRIGGER afec_test_fail_audit BEFORE INSERT ON audit_log FOR EACH ROW BEGIN IF NEW.entity_type IN ('user','movement','wishlist','commodity_source') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Forced atomicity check'; END IF; END"); echo 'ok'; exit;
}
if($action==='clear_failure') { $db->exec('DROP TRIGGER IF EXISTS afec_test_fail_audit'); echo 'ok'; exit; }
$data=[];
foreach(['users','commanders','movements','operations','audit_log','wishlist_entries','commodity_sources'] as $table) { $data[$table]=(int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn(); }
$data['original_movements_hash']=hash('sha256',json_encode($db->query('SELECT * FROM movements WHERE id<=6 ORDER BY id')->fetchAll(),JSON_THROW_ON_ERROR));
echo json_encode($data,JSON_THROW_ON_ERROR);
