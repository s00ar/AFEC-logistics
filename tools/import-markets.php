<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

use Afec\Repositories\MarketRepository;
use Afec\Repositories\UserRepository;
use Afec\Services\MarketService;
use Afec\Security\UserRole;

$app=null;
try {
    $options=getopt('',['stock:','summary:','system:','user:','apply','allow-production']);
    if(!isset($options['user']) || !is_string($options['user'])) { throw new RuntimeException('Uso: php tools/import-markets.php --user=USUARIO [--stock=CSV] [--summary=CSV] [--system=SISTEMA] [--apply --allow-production]'); }
    $app=require dirname(__DIR__).'/src/bootstrap.php';
    if(!(new MarketRepository($app->database))->ready()) { throw new RuntimeException('Falta aplicar 009_system_markets.sql.'); }
    $user=(new UserRepository($app->database))->findByUsername($options['user']);
    if(!$user || !$user['active'] || UserRole::canonical($user['role'])===null) { throw new RuntimeException('Se requiere un Comandante o Administrador activo.'); }
    $read=static function(string $field) use($options): ?string {
        if(!isset($options[$field])) { return null; }
        if(!is_string($options[$field]) || !is_file($options[$field]) || filesize($options[$field])>1048576) { throw new RuntimeException('Archivo CSV ausente o mayor de 1 MiB.'); }
        return file_get_contents($options[$field]);
    };
    $stock=$read('stock'); $summary=$read('summary'); $system=$options['system']??null;
    $service=new MarketService($app->database); $preview=$service->preview($stock,$summary,$system);
    if(!isset($options['apply'])) { echo json_encode(['dry_run'=>true,'data'=>$preview],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL; exit; }
    if($app->config['app']['environment']==='production' && !isset($options['allow-production'])) { throw new RuntimeException('Producción requiere autorización específica, respaldo y --allow-production.'); }
    $result=$service->import(UserRepository::publicUser($user),(int)$user['auth_version'],'markets-'.$preview['review_hash'],$stock,$summary,$system,isset($options['stock'])?basename($options['stock']):null,isset($options['summary'])?basename($options['summary']):null,$preview['review_hash']);
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $error) {
    if($app!==null) { $app->logError($error,'market-cli-'.bin2hex(random_bytes(8))); }
    fwrite(STDERR,($error instanceof \PDOException?'No se pudo importar. Revisá la configuración y los logs privados.':$error->getMessage()).PHP_EOL); exit(1);
}
