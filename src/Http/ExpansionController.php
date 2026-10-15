<?php
declare(strict_types=1);
namespace Afec\Http;

use Afec\App;
use Afec\Repositories\LogisticsRepository;
use Afec\Repositories\PlanningRepository;
use Afec\Repositories\UserRepository;
use Afec\Services\LogisticsService;
use Afec\Services\UserService;
use Afec\Validation\LogisticsInput as Input;

final class ExpansionController
{
    public static function dispatch(App $app,string $route): never
    {
        $method=$_SERVER['REQUEST_METHOD']??'GET';
        $actor=str_starts_with($route,'admin/')?$app->auth()->requireAdmin():$app->auth()->requireUser();
        $repo=new LogisticsRepository($app->database);
        $id=static fn(): string=>Request::positiveId($_GET['id']??null);
        $installation=static fn(): string=>Request::positiveId($_GET['installation_id']??null);
        $commodity=static fn(): string=>Request::positiveId($_GET['commodity_id']??null);
        $page=Request::page($_GET['list_page']??null);
        if($method==='GET') {
            switch($route) {
                case 'admin/users':
                    $listing=(new UserRepository($app->database))->listing($page);
                    Response::json(['data'=>$listing['items'],'pagination'=>['page'=>$page,'has_more'=>$listing['has_more']]]);
                case 'admin/user':
                    $row=(new UserRepository($app->database))->findById($id()) ?? throw new HttpError(404,'not_found','No se encontró la cuenta.');
                    Response::json(['data'=>UserRepository::adminView($row)]);
                case 'admin/roles':
                    Response::json(['data'=>['admin'=>'Administrador: toda la gestión, incluidas cuentas y roles','viewer'=>'Comandante: planificación, catálogo y logística, incluidos aportes de terceros']]);
                case 'wishlist':
                case 'wishlist/destinations':
                    $allowed=$route==='wishlist'?['route','scope','list_page','selection_page']:['route','scope','commodity_id','list_page'];
                    if(array_diff(array_keys($_GET),$allowed)) { throw new HttpError(422,'validation','La lista personal siempre corresponde a la cuenta autenticada.'); }
                    $scope=Input::choice($_GET,'scope',['personal','fleet']);
                    $data=$route==='wishlist'?$repo->wishlist($scope,$actor['id'],$page,Request::page($_GET['selection_page']??null)):$repo->destinations($scope,$actor['id'],$commodity(),$page);
                    Response::json(['data'=>$data]);
                case 'sources':
                    Response::json(['data'=>$repo->sources($commodity(),$page,Request::flag($_GET['include_inactive']??null))]);
                case 'movements':
                    (new PlanningRepository($app->database))->installation($installation());
                    Response::json(['data'=>$repo->movements($installation(),$actor,$page)]);
                case 'logistics/options':
                    Response::json(['data'=>$repo->choices()]);
                case 'quick/options':
                    Response::json(['data'=>(new \Afec\Repositories\CommanderLoadRepository($app->database))->options($actor,$page)]);
                case 'carrier/loads':
                    \Afec\Validation\PlanningInput::fields($_GET,['route','scope']);
                    $scope=Input::choice($_GET,'scope',['personal','fleet']);
                    Response::json(['data'=>(new \Afec\Repositories\CarrierLoadRepository($app->database))->listing($actor['id'],$scope)]);
                case 'commander/load':
                    Response::json(['data'=>(new \Afec\Repositories\CommanderLoadRepository($app->database))->find($id())]);
                case 'operation':
                    $key=$_GET['key']??null; $scope=$_GET['scope']??null;
                    if(!is_string($key)||!preg_match('/^[A-Za-z0-9_-]{16,100}$/D',$key)||!is_string($scope)||!preg_match('/^(planning|logistics|admin\.user|account)\.[a-z.]{1,60}$/D',$scope)) { throw new HttpError(422,'validation','Operación no válida.'); }
                    $db=$app->database->connection(); $db->beginTransaction();
                    try {
                        $q=$db->prepare('SELECT id FROM users WHERE id=? FOR UPDATE'); $q->execute([$actor['id']]);
                        $q=$db->prepare('SELECT result_json FROM operations WHERE actor_user_id=? AND scope=? AND idempotency_key=?'); $q->execute([$actor['id'],$scope,$key]);
                        $saved=$q->fetchColumn(); $db->commit();
                    } catch(\Throwable $error) { if($db->inTransaction()) { $db->rollBack(); } throw $error; }
                    Response::json(['data'=>$saved?json_decode($saved,true,32,JSON_THROW_ON_ERROR):null]);
            }
            throw new HttpError(405,'method_not_allowed','Método no permitido.');
        }
        $allowed=match($route) {
            'admin/users','admin/user/password','admin/user/restore','wishlist/entries','sources','source/restore','deliveries','movement/withdraw'=>['POST'],
            'admin/user','source'=>['PATCH','DELETE'],
            'installation/status','commander/load'=>['PATCH'], 'quick/withdraw','carrier/load','commander/identity'=>['POST'], 'wishlist/entry'=>['DELETE'],default=>[],
        };
        if(!in_array($method,$allowed,true)) { header('Allow: '.implode(', ',$allowed)); throw new HttpError(405,'method_not_allowed','Método no permitido.'); }
        $app->session()->verifyCsrf(); $body=Request::json(); $key=$_SERVER['HTTP_IDEMPOTENCY_KEY']??''; $auth=$app->session()->authVersion();
        $users=new UserService($app->database); $service=new LogisticsService($app->database);
        try {
            $result=match($route) {
                'admin/users'=>$users->write($actor,$auth,$key,'create',null,$body),
                'admin/user'=>$users->write($actor,$auth,$key,$method==='PATCH'?'update':'delete',$id(),$body),
                'admin/user/password'=>$users->write($actor,$auth,$key,'password',$id(),$body),
                'admin/user/restore'=>$users->write($actor,$auth,$key,'restore',$id(),$body),
                'installation/status'=>$service->status($actor,$auth,$key,$id(),$body),
                'wishlist/entries'=>$service->listChange($actor,$auth,$key,$installation(),$body),
                'wishlist/entry'=>$service->listChange($actor,$auth,$key,$installation(),$body,true),
                'sources'=>$service->sourceChange($actor,$auth,$key,'create',null,$body),
                'source'=>$service->sourceChange($actor,$auth,$key,$method==='PATCH'?'update':'delete',$id(),$body),
                'source/restore'=>$service->sourceChange($actor,$auth,$key,'restore',$id(),$body),
                'deliveries'=>$service->deliver($actor,$auth,$key,$installation(),$commodity(),$body),
                'movement/withdraw'=>$service->withdraw($actor,$auth,$key,$id(),$body),
                'quick/withdraw'=>$service->quickWithdraw($actor,$auth,$key,$installation(),$commodity(),$body),
                'commander/load'=>(new \Afec\Services\CommanderLoadService($app->database))->save($actor,$auth,$key,$id(),$body),
                'commander/identity'=>(new \Afec\Services\CommanderLoadService($app->database))->identity($actor,$auth,$key,$body),
                'carrier/load'=>(new \Afec\Services\CarrierLoadService($app->database))->save($actor,$auth,$key,$body),
            };
        } catch(\PDOException $error) {
            if(in_array($route,['deliveries','carrier/load'],true) && (int)($error->errorInfo[1]??0)===1062) { throw new HttpError(409,'carrier_exists','Ese código de carrier ya existe; seleccioná el carrier registrado.'); }
            throw $error;
        }
        Response::json(['data'=>$result['data'],'operation_id'=>$result['operation_id'],'replayed'=>$result['replayed']],$result['status']);
    }
}
