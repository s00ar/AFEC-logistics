<?php
declare(strict_types=1);
namespace Afec\Services;

use Afec\Database;
use Afec\Http\HttpError;
use Afec\Repositories\CarrierLoadRepository;
use Afec\Repositories\LogisticsRepository;
use Afec\Repositories\PlanningRepository;
use Afec\Validation\PlanningInput as Input;
use Afec\Validation\LogisticsInput as L;

final class CarrierLoadService
{
    public function __construct(private readonly Database $database) {}

    public function save(array $actor,int $authVersion,string $key,array $input): array
    {
        Input::fields($input,['scope','carrier_id','carrier_name','carrier_code','commodity_id','quantity','version']);
        $data=['scope'=>L::choice($input,'scope',['personal','fleet']), 'carrier_id'=>L::id($input,'carrier_id',true),
            'carrier_name'=>Input::text($input,'carrier_name',160), 'carrier_code'=>Input::text($input,'carrier_code',40),
            'commodity_id'=>L::id($input,'commodity_id'), 'quantity'=>($input['quantity']??null)==='0'?'0':L::amount($input), 'version'=>Input::version($input,true)];
        if (($data['carrier_id']!==null && $data['carrier_name']!==null) || ($data['carrier_id']===null && $data['carrier_name']===null) || ($data['carrier_code']!==null && $data['carrier_name']===null)) {
            throw new HttpError(422,'validation','Elegí un carrier existente o indicá el nombre de uno nuevo.');
        }
        return (new MutationService($this->database))->run($actor,$authVersion,['admin', 'viewer'],'logistics.carrier.load',$key,$data,
            function(string $op) use($actor,$data): array {
                $repo=new LogisticsRepository($this->database);
                $carrier=$data['carrier_id'];
                if ($carrier===null) {
                    $repo->execute('INSERT INTO carriers (name,code) VALUES (?,?)',[$data['carrier_name'],$data['carrier_code']]);
                    $carrier=$this->database->connection()->lastInsertId();
                }
                if (!$repo->rows('SELECT id FROM carriers WHERE id=? AND active=1 FOR UPDATE',[$carrier])) { throw new HttpError(422,'validation','El carrier no está activo.'); }
                (new PlanningRepository($this->database))->activeCommodity($data['commodity_id']);
                $loads=new CarrierLoadRepository($this->database);
                $before=$loads->current($actor['id'],$carrier,$data['commodity_id']);
                if (($before['version']??'0')!==$data['version']) { throw new HttpError(409,'stale_version','La carga cambió. Actualizá antes de guardar.'); }
                if ($data['version']==='4294967295') { throw new HttpError(409,'version_exhausted','Se alcanzó el límite técnico de versiones.'); }
                $after=['carrier_id'=>$carrier,'commodity_id'=>$data['commodity_id'],'scope'=>$data['scope'],'quantity'=>$data['quantity'],'version'=>(string)((int)$data['version']+1)];
                (new PlanningRepository($this->database))->audit('carrier_load',$carrier,'stock',$before,$after,$actor['id'],'Actualización de carga total en carrier',$op);
                return ['data'=>$after,'status'=>200];
            });
    }
}
