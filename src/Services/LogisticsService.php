<?php
declare(strict_types=1);
namespace Afec\Services;

use Afec\Database;
use Afec\Http\HttpError;
use Afec\Repositories\LogisticsRepository;
use Afec\Repositories\PlanningRepository;
use Afec\Validation\PlanningInput as Input;
use Afec\Validation\LogisticsInput as L;
use PDOException;

final class LogisticsService
{
    private LogisticsRepository $repo;
    private PlanningRepository $planning;
    public function __construct(private readonly Database $database)
    {
        $this->repo=new LogisticsRepository($database); $this->planning=new PlanningRepository($database);
    }
    private function run(array $actor,int $authVersion,string $scope,string $key,array $payload,callable $write,array $roles=['admin', 'viewer']): array
    { return (new MutationService($this->database))->run($actor,$authVersion,$roles,$scope,$key,$payload,$write); }
    private function version(array $row,string $version): void
    { if($row['version']!==$version || $version==='4294967295') { throw new HttpError(409,'stale_version','Los datos cambiaron. Actualizá antes de guardar.'); } }
    public function status(array $actor,int $authVersion,string $key,string $id,array $input): array
    {
        Input::fields($input,['construction_status','version','reason','confirmed']);
        $data=['id'=>$id,'construction_status'=>L::choice($input,'construction_status',['under_construction','completed']),'version'=>Input::version($input),'reason'=>Input::text($input,'reason',1000,true,true)];
        if($data['construction_status']==='completed') { Input::confirmation($input); }
        return $this->run($actor,$authVersion,'logistics.status',$key,$data,function(string $op) use($actor,$id,$data): array {
            [$parent,$before]=$this->planning->lockInstallation($id); $this->version($before,$data['version']);
            if($parent['archived_at'] || $before['archived_at']) { throw new HttpError(409,'archived_parent','Restaurá la instalación y su sistema antes de cambiar el estado.'); }
            $this->repo->execute('UPDATE installations SET construction_status=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?',[$data['construction_status'],$id]);
            $after=$this->planning->installation($id); $this->planning->audit('installation',$id,'status',$before,$after,$actor['id'],$data['reason'],$op);
            return ['data'=>$after,'status'=>200];
        },['admin', 'viewer']);
    }
    public function listChange(array $actor,int $authVersion,string $key,string $id,array $input,bool $remove=false): array
    {
        Input::fields($input,$remove?['scope','version','reason']:['scope']);
        $scope=L::choice($input,'scope',['personal','fleet']); $listKey=LogisticsRepository::listKey($scope,$actor['id']);
        $data=['installation_id'=>$id,'scope'=>$scope];
        if($remove) { $data['version']=Input::version($input); $data['reason']=Input::text($input,'reason',1000,true,true); }
        return $this->run($actor,$authVersion,$remove?'logistics.list.remove':'logistics.list.add',$key,$data,function(string $op) use($actor,$id,$scope,$listKey,$data,$remove): array {
            [$parent,$installation]=$this->planning->lockInstallation($id);
            $before=$this->repo->entry($listKey,$id,true);
            if($remove) {
                if(!$before) { throw new HttpError(404,'not_found','La instalación no está en esta lista.'); }
                $this->version($before,$data['version']);
                if(!(bool)$before['active']) { throw new HttpError(409,'inactive','La instalación ya se retiró de esta lista.'); }
                $this->repo->execute('UPDATE wishlist_entries SET active=0,version=version+1,updated_by_user_id=?,updated_at=UTC_TIMESTAMP(6) WHERE list_key=? AND installation_id=?',[$actor['id'],$listKey,$id]);
            } else {
                if($parent['archived_at'] || $installation['archived_at'] || $installation['construction_status']==='completed') { throw new HttpError(409,'excluded_installation','Las instalaciones terminadas o archivadas no se agregan a listas activas.'); }
                if($before && (bool)$before['active']) { return ['data'=>$before+['scope'=>$scope,'already_added'=>true],'status'=>200]; }
                if($before) {
                    if($before['version']==='4294967295') { throw new HttpError(409,'version_exhausted','Se alcanzó el límite de versiones.'); }
                    $this->repo->execute('UPDATE wishlist_entries SET active=1,version=version+1,updated_by_user_id=?,updated_at=UTC_TIMESTAMP(6) WHERE list_key=? AND installation_id=?',[$actor['id'],$listKey,$id]);
                } else {
                    $this->repo->execute('INSERT INTO wishlist_entries (list_key,owner_user_id,installation_id,added_by_user_id,updated_by_user_id) VALUES (?,?,?,?,?)',[$listKey,$scope==='personal'?$actor['id']:null,$id,$actor['id'],$actor['id']]);
                }
            }
            $after=$this->repo->entry($listKey,$id);
            $this->planning->audit('wishlist',$id,$remove?'remove':'add',$before,$after,$actor['id'],$data['reason']??'Selección de instalación en lista '.$scope,$op);
            return ['data'=>$after+['scope'=>$scope],'status'=>200];
        });
    }
    public function sourceChange(array $actor,int $authVersion,string $key,string $action,?string $id,array $input): array
    {
        Input::fields($input,match($action){'create'=>['commodity_id','system_name','facility_name','facility_type','notes','reason'],'update'=>['system_name','facility_name','facility_type','notes','version','reason'],default=>['version','reason','confirmed']});
        $data=['reason'=>Input::text($input,'reason',1000,true,true)];
        if($action==='create') { $data['commodity_id']=L::id($input,'commodity_id'); }
        else { $data['id']=$id; $data['version']=Input::version($input); }
        if(in_array($action,['create','update'],true)) {
            $data+=['system_name'=>Input::text($input,'system_name',255,true),'facility_name'=>Input::text($input,'facility_name',255,true),'facility_type'=>L::choice($input,'facility_type',['station','settlement']),'notes'=>Input::text($input,'notes',1000,false,true)];
        }
        if($action==='delete') { Input::confirmation($input); }
        try {
            return $this->run($actor,$authVersion,'logistics.source.'.$action,$key,$data,function(string $op) use($actor,$data,$id,$action): array {
                $before=null;
                if($action==='create') {
                    $this->planning->activeCommodity($data['commodity_id']);
                    $this->repo->execute('INSERT INTO commodity_sources (commodity_id,system_name,facility_name,facility_type,notes,created_by_user_id,updated_by_user_id) VALUES (?,?,?,?,?,?,?)',[$data['commodity_id'],$data['system_name'],$data['facility_name'],$data['facility_type'],$data['notes'],$actor['id'],$actor['id']]);
                    $id=$this->database->connection()->lastInsertId();
                } else {
                    $before=$this->repo->source($id,true); $this->version($before,$data['version']);
                    if($action==='update') {
                        if(!(bool)$before['active']) { throw new HttpError(409,'inactive','Restaurá el lugar antes de editarlo.'); }
                        $this->repo->execute('UPDATE commodity_sources SET system_name=?,facility_name=?,facility_type=?,notes=?,updated_by_user_id=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?',[$data['system_name'],$data['facility_name'],$data['facility_type'],$data['notes'],$actor['id'],$id]);
                    } else {
                        if((bool)$before['active']===($action==='restore')) { throw new HttpError(409,'archive_state','El estado del lugar cambió.'); }
                        $this->repo->execute('UPDATE commodity_sources SET active=?,updated_by_user_id=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?',[(int)($action==='restore'),$actor['id'],$id]);
                    }
                }
                $after=$this->repo->source($id); $this->planning->audit('commodity_source',$id,$action,$before,$after,$actor['id'],$data['reason'],$op);
                return ['data'=>$after,'status'=>$action==='create'?201:200];
            });
        } catch(PDOException $error) { if((int)($error->errorInfo[1]??0)===1062) { throw new HttpError(409,'source_exists','Este lugar ya está registrado para la mercancía. Consultá los retirados para restaurarlo.'); } throw $error; }
    }
    public function deliver(array $actor,int $authVersion,string $key,string $installation,string $commodity,array $input): array
    {
        Input::fields($input,['quantity','use_standard_load','use_remaining','commander_id','commander_name','carrier_id','carrier_name','carrier_code','occurred_at','reason']);
        $standard = self::standardFlag($input);
        $remaining = array_key_exists('use_remaining',$input)?$input['use_remaining']:false;
        if (!is_bool($remaining) || ($remaining && ($standard || array_key_exists('quantity',$input)))) { throw new HttpError(422,'validation','Elegí cargar faltante, palada estándar o cantidad manual, sin combinarlas.'); }
        $data=['installation_id'=>$installation,'commodity_id'=>$commodity,'quantity'=>($standard || $remaining)?null:L::amount($input),'commander_id'=>L::id($input,'commander_id',true),'commander_name'=>Input::text($input,'commander_name',160),'carrier_id'=>L::id($input,'carrier_id',true),'carrier_name'=>Input::text($input,'carrier_name',160),'carrier_code'=>Input::text($input,'carrier_code',40),'occurred_at'=>L::date($input),'reason'=>Input::text($input,'reason',1000,false,true)];
        // Keep the exact hash of pre-upgrade manual requests for pending retries.
        if ($standard) { $data['use_standard_load']=true; }
        if ($remaining) { $data['use_remaining']=true; }
        if($data['commander_id'] && $data['commander_name'] || $data['carrier_id'] && $data['carrier_name'] || $data['carrier_code'] && !$data['carrier_name']) { throw new HttpError(422,'validation','Elegí una identidad existente o ingresá una nueva, sin combinar ambas.'); }
        return $this->run($actor,$authVersion,'logistics.delivery',$key,$data,function(string $op) use($actor,$data,$installation,$commodity): array {
            [$parent,$place]=$this->planning->lockInstallation($installation);
            if($parent['archived_at'] || $place['archived_at'] || $place['construction_status']==='completed') { throw new HttpError(409,'excluded_installation','Reabrí/restaurá la instalación antes de registrar entregas.'); }
            $requirement=$this->planning->requirement($installation,$commodity,true);
            if(!$requirement || !(bool)$requirement['active']) { throw new HttpError(409,'inactive','Seleccioná un objetivo activo.'); }
            $commander=$data['commander_id'];
            if(!$commander) { $commander=$data['commander_name']?$this->repo->newCommander($data['commander_name']):$this->repo->ownCommander($actor); }
            if(!$this->repo->rows('SELECT id FROM commanders WHERE id=? AND active=1',[$commander])) { throw new HttpError(422,'validation','El comandante no está activo.'); }
            $quantity=($data['use_standard_load']??false)?(new \Afec\Repositories\CommanderLoadRepository($this->database))->amount($commander):$data['quantity'];
            $carrier=$data['carrier_id'];
            if($data['carrier_name']) {
                $this->repo->execute('INSERT INTO carriers (name,code) VALUES (?,?)',[$data['carrier_name'],$data['carrier_code']]); $carrier=$this->database->connection()->lastInsertId();
            }
            if($carrier && !$this->repo->rows('SELECT id FROM carriers WHERE id=? AND active=1',[$carrier])) { throw new HttpError(422,'validation','El carrier no está activo.'); }
            $pending=$this->repo->rows('SELECT CAST(GREATEST(CAST(? AS DECIMAL(65,0))-COALESCE(SUM(delta_quantity),0),0) AS CHAR) AS pending_quantity FROM movements WHERE installation_id=? AND commodity_id=?',[$requirement['required_quantity'],$installation,$commodity])[0]['pending_quantity'];
            if ($data['use_remaining']??false) {
                if ($pending==='0') { throw new HttpError(409,'nothing_pending','Esta mercancía ya está completa. Actualizá los datos para ver su estado.'); }
                $quantity=L::amount(['quantity'=>$pending]);
            }
            $excess=L::compare($quantity,$pending)>0;
            $this->repo->execute("INSERT INTO movements (installation_id,commodity_id,created_by_user_id,commander_id,carrier_id,delta_quantity,movement_type,operation_id,occurred_at,reason) VALUES (?,?,?,?,?,?,'delivery',?,?,?)",[$installation,$commodity,$actor['id'],$commander,$carrier,$quantity,$op,$data['occurred_at']??gmdate('Y-m-d H:i:s'),$data['reason']]);
            $id=$this->database->connection()->lastInsertId(); $after=$this->repo->movement($id);
            $this->planning->audit('movement',$id,'delivery',null,$after,$actor['id'],$data['reason']??'Entrega recibida en instalación',$op);
            return ['data'=>$after+['system_id'=>$parent['id'],'exceeds_pending'=>$excess],'status'=>201];
        });
    }
    public function withdraw(array $actor,int $authVersion,string $key,string $id,array $input): array
    {
        Input::fields($input,['quantity','reason','confirmed']); Input::confirmation($input);
        $data=['id'=>$id,'quantity'=>L::amount($input),'reason'=>Input::text($input,'reason',1000,true,true)];
        return $this->run($actor,$authVersion,'logistics.withdraw',$key,$data,function(string $op) use($actor,$id,$data): array {
            $original=$this->repo->movement($id); [$parent,$place]=$this->planning->lockInstallation($original['installation_id']);
            $this->planning->requirement($original['installation_id'],$original['commodity_id'],true);
            $original=$this->repo->movement($id,true);
            if($original['delta_quantity'][0]==='-' || $original['reverses_movement_id']!==null) { throw new HttpError(422,'validation','Seleccioná una entrega o saldo positivo original.'); }
            $available=$this->repo->reversible($id);
            if($available[0]==='-' || L::compare($data['quantity'],$available)>0) { throw new HttpError(409,'withdrawal_limit','La cantidad supera el saldo que conserva este movimiento.'); }
            $balance=$this->repo->rows('SELECT CAST(COALESCE(SUM(delta_quantity),0) AS CHAR) AS balance_quantity FROM movements WHERE installation_id=? AND commodity_id=?',[$original['installation_id'],$original['commodity_id']])[0]['balance_quantity'];
            if($balance[0]==='-' || L::compare($data['quantity'],$balance)>0) { throw new HttpError(409,'negative_balance','La corrección dejaría un saldo negativo.'); }
            $this->repo->execute("INSERT INTO movements (installation_id,commodity_id,created_by_user_id,commander_id,carrier_id,delta_quantity,movement_type,reverses_movement_id,operation_id,occurred_at,reason) VALUES (?,?,?,?,?,?,'reversal',?,?,UTC_TIMESTAMP(6),?)",[$original['installation_id'],$original['commodity_id'],$actor['id'],$original['commander_id'],$original['carrier_id'],'-'.$data['quantity'],$id,$op,$data['reason']]);
            $new=$this->database->connection()->lastInsertId(); $after=$this->repo->movement($new);
            $this->planning->audit('movement',$new,'withdraw',null,$after,$actor['id'],$data['reason'],$op);
            return ['data'=>$after+['system_id'=>$parent['id']],'status'=>201];
        });
    }

    private static function standardFlag(array $input): bool
    {
        $standard=array_key_exists('use_standard_load',$input)?$input['use_standard_load']:false;
        if (!is_bool($standard) || ($standard && array_key_exists('quantity',$input))) { throw new HttpError(422,'validation','Elegí palada estándar o cantidad manual, sin combinarlas.'); }
        return $standard;
    }

    public function quickWithdraw(array $actor,int $authVersion,string $key,string $installation,string $commodity,array $input): array
    {
        Input::fields($input,['quantity','use_standard_load','commander_id','reason']);
        $standard=self::standardFlag($input);
        $data=['installation_id'=>$installation,'commodity_id'=>$commodity,'commander_id'=>L::id($input,'commander_id'),
            'use_standard_load'=>$standard,'quantity'=>$standard?null:L::amount($input),'reason'=>Input::text($input,'reason',1000,false,true) ?? 'Resta de stock desde panel de material'];
        return $this->run($actor,$authVersion,'logistics.quick.withdraw',$key,$data,function(string $op) use($actor,$installation,$commodity,$data): array {
            [$parent,$place]=$this->planning->lockInstallation($installation);
            $this->planning->requirement($installation,$commodity,true);
            $commander=(new \Afec\Repositories\CommanderLoadRepository($this->database))->find($data['commander_id'],true);
            if (!$commander['active']) { throw new HttpError(422,'inactive','El comandante no está activo.'); }
            $quantity=$data['use_standard_load']?(new \Afec\Repositories\CommanderLoadRepository($this->database))->amount($data['commander_id']):$data['quantity'];
            $balance=$this->repo->rows('SELECT CAST(COALESCE(SUM(delta_quantity),0) AS CHAR) AS balance_quantity FROM movements WHERE installation_id=? AND commodity_id=?',[$installation,$commodity])[0]['balance_quantity'];
            if ($balance[0]==='-' || L::compare($quantity,$balance)>0) { throw new HttpError(409,'negative_balance','La resta supera lo entregado y dejaría un saldo negativo.'); }
            // All writers take the installation lock first. Oldest positive entries are compensated first.
            $originals=$this->repo->rows('SELECT m.id,m.carrier_id,CAST(m.delta_quantity+COALESCE((SELECT SUM(r.delta_quantity) FROM movements r WHERE r.reverses_movement_id=m.id),0) AS CHAR) AS available_quantity FROM movements m WHERE m.installation_id=? AND m.commodity_id=? AND m.commander_id=? AND m.delta_quantity>0 AND m.reverses_movement_id IS NULL ORDER BY m.id FOR UPDATE',[$installation,$commodity,$data['commander_id']]);
            $remaining=(int)$quantity; $parts=[];
            foreach ($originals as $row) {
                $available=$row['available_quantity'];
                if ($available[0]==='-' || $available==='0') { continue; }
                $take=min($remaining,(int)$available); $parts[]=['id'=>$row['id'],'carrier_id'=>$row['carrier_id'],'quantity'=>(string)$take]; $remaining-=$take;
                if (count($parts)>65535) { throw new HttpError(409,'entry_limit','La corrección abarca demasiadas entregas. Usá restas más pequeñas.'); }
                if ($remaining===0) { break; }
            }
            if ($remaining!==0) { throw new HttpError(409,'withdrawal_limit','La cantidad supera las entregas que conserva el comandante seleccionado.'); }
            $ids=[];
            foreach ($parts as $index=>$part) {
                $this->repo->execute("INSERT INTO movements (installation_id,commodity_id,created_by_user_id,commander_id,carrier_id,delta_quantity,movement_type,reverses_movement_id,operation_id,entry_number,occurred_at,reason) VALUES (?,?,?,?,?,?,'reversal',?,?,?,UTC_TIMESTAMP(6),?)",[$installation,$commodity,$actor['id'],$data['commander_id'],$part['carrier_id'],'-'.$part['quantity'],$part['id'],$op,$index+1,$data['reason']]);
                $new=$this->database->connection()->lastInsertId(); $after=$this->repo->movement($new); $ids[]=$new;
                $this->planning->audit('movement',$new,'withdraw',null,$after,$actor['id'],$data['reason'],$op);
            }
            return ['data'=>['movement_ids'=>$ids,'quantity'=>$quantity,'commander_id'=>$data['commander_id'],'installation_id'=>$installation,'commodity_id'=>$commodity,'system_id'=>$parent['id']],'status'=>201];
        });
    }
}
