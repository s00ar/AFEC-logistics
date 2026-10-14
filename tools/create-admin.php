<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$app = require dirname(__DIR__) . '/src/bootstrap.php';
$options = getopt('', ['username:', 'name:', 'password-file:']);
$username = $options['username'] ?? '';
$name = $options['name'] ?? '';
$file = $options['password-file'] ?? '';
if (!is_string($username)||!preg_match('/^[a-zA-Z0-9_.@+-]{3,120}$/D',$username)||!is_string($name)||trim($name)===''||mb_strlen($name)>160||!is_string($file)||!is_file($file)) {
    fwrite(STDERR,"Uso: php tools/create-admin.php --username=admin --name=Nombre --password-file=RUTA_PRIVADA\n"); exit(1);
}
$password = rtrim(file_get_contents($file),"\r\n");
if (strlen($password)<12||strlen($password)>72||str_contains($password,"\0")) { fwrite(STDERR,"La contraseña debe tener entre 12 y 72 bytes.\n"); exit(1); }
$db=null; $lock=null; $code=0;
try {
    $db=$app->database->connection();
    $lock='afec-admin-'.substr(hash('sha256',$app->config['database']['name']),0,48);
    $q=$db->prepare('SELECT GET_LOCK(?,2)'); $q->execute([$lock]);
    if ((int)$q->fetchColumn()!==1) { $lock=null; throw new RuntimeException('Hay otra inicialización en curso.'); }
    $db->beginTransaction();
    if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()!==0) { throw new RuntimeException('Ya existen cuentas; solo se permite crear el primer administrador.'); }
    $q=$db->prepare("INSERT INTO users (username,display_name,password_hash,role) VALUES (?,?,?,'admin')");
    $q->execute([$username,trim($name),password_hash($password,PASSWORD_DEFAULT)]);
    $id=$db->lastInsertId();
    $q=$db->prepare('INSERT INTO audit_log (actor_user_id,action,entity_type,entity_id,changes_json,reason) VALUES (?,?,?,?,?,?)');
    $q->execute([$id,'bootstrap_admin','user',$id,json_encode(['role'=>'admin']),'Creación inicial mediante herramienta CLI privada']);
    $db->commit(); echo "Primer administrador creado; no hay contraseñas predeterminadas.\n";
} catch (Throwable $error) {
    if ($db!==null&&$db->inTransaction()) { $db->rollBack(); }
    $app->logError($error,'bootstrap-'.bin2hex(random_bytes(8)));
    fwrite(STDERR,($error instanceof PDOException ? 'No se pudo crear el administrador; revisar conexión y esquema.' : $error->getMessage()).PHP_EOL); $code=1;
} finally {
    if ($db!==null&&$lock!==null) { $q=$db->prepare('SELECT RELEASE_LOCK(?)'); $q->execute([$lock]); }
}
exit($code);
