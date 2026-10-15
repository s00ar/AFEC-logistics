<?php
declare(strict_types=1);
namespace Afec\Services;

use Afec\Database;
use Afec\Http\HttpError;
use Afec\Http\Request;
use Afec\Repositories\CommodityRepository;
use Afec\Repositories\MarketRepository;
use Afec\Repositories\PlanningRepository;
use Afec\Validation\PlanningInput as Input;

final class MarketService
{
    private readonly MarketRepository $markets;
    public function __construct(private readonly Database $database) { $this->markets=new MarketRepository($database); }
    private function lock(): void
    {
        if (!$this->markets->rows("SELECT name FROM permission_locks WHERE name='system_markets' FOR UPDATE")) { throw new HttpError(503,'market_schema','Falta aplicar la migración 009 de mercados.'); }
    }
    private function mapping(array $rows): array
    {
        $index=[];
        foreach ((new CommodityRepository($this->database))->rows() as $commodity) {
            foreach(array_filter([$commodity['code'],$commodity['name_es'],$commodity['name_en'],...$commodity['aliases']]) as $alias) { $index[MarketCsv::key($alias)][$commodity['id']]=$commodity; }
        }
        $mapped=[];
        foreach($rows as $row) {
            $key=$row['product_key'];
            $matches=$index[$key]??[];
            if(count($matches)>1) { throw new HttpError(409,'ambiguous_commodity','Equivalencia ambigua para '.$row['mercaderia'].'. Corregí los alias del catálogo antes de importar.'); }
            $match=$matches?reset($matches):null;
            $mapped[$key]=['original_name'=>$row['mercaderia'],'commodity_id'=>$match['id']??null,'commodity_name'=>$match['name_es']??null];
        }
        return $mapped;
    }
    public function preview(?string $stock, ?string $summary, ?string $context): array
    {
        $parsed=MarketCsv::parse($stock,$summary,$context); $mapping=$this->mapping($parsed['stock']);
        $stations=[]; foreach([...$parsed['stock'],...$parsed['summary']] as $row) { $stations[$row['station_key']]=true; }
        return ['review_hash'=>$parsed['hash'],'stock_rows'=>count($parsed['stock']),'summary_rows'=>count($parsed['summary']),
            'stations'=>count($stations),'products'=>count($mapping),'mapped'=>count(array_filter($mapping,static fn($r)=>$r['commodity_id']!==null)),
            'unmatched'=>array_values(array_column(array_filter($mapping,static fn($r)=>$r['commodity_id']===null),'original_name')),
            'system_context'=>$parsed['system_context'],'existing_import'=>$this->markets->rows('SELECT CAST(id AS CHAR) AS id FROM market_imports WHERE content_sha256=?',[$parsed['hash']])[0]['id']??null];
    }
    private function station(array $row): string
    {
        $this->markets->execute('INSERT INTO market_stations (identity_key,system_name,name,body_name,metadata_observed_on) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)',
            [$row['station_key'],$row['sistema'],$row['estacion'],$row['cuerpo']?:null,$row['observed_on']]);
        $id=(string)$this->database->connection()->lastInsertId();
        $this->markets->execute('UPDATE market_stations SET body_name=?,metadata_observed_on=?,updated_at=UTC_TIMESTAMP(6),version=version+1 WHERE id=? AND ? IS NOT NULL AND (metadata_observed_on IS NULL OR metadata_observed_on<?) AND NOT (body_name <=> ?)',
            [$row['cuerpo']?:null,$row['observed_on'],$id,$row['observed_on'],$row['observed_on'],$row['cuerpo']?:null]);
        return $id;
    }
    private function observation(?string $import, string $station, string $product, array $row, bool $applied, string $actor): string
    {
        $this->markets->execute('INSERT INTO market_observations (import_id,station_id,product_id,original_name,category,quantity,available,verified,observed_on,source,verification,applied,actor_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$import,$station,$product,$row['mercaderia'],$row['categoria']?:null,$row['quantity'],$row['available'],$row['verified'],$row['observed_on'],$row['fuente'],$row['verificacion'],(int)$applied,$actor]);
        return (string)$this->database->connection()->lastInsertId();
    }
    public function import(array $actor, int $authVersion, string $key, ?string $stock, ?string $summary, ?string $context, ?string $stockName, ?string $summaryName, string $reviewHash): array
    {
        $parsed=MarketCsv::parse($stock,$summary,$context);
        if (!hash_equals($parsed['hash'],$reviewHash)) { throw new HttpError(409,'market_review','Los archivos o el sistema cambiaron. Volvé a validar antes de importar.'); }
        return (new MutationService($this->database))->run($actor,$authVersion,['admin','viewer'],'markets.import',$key,['hash'=>$parsed['hash']],function(string $operation) use($parsed,$stock,$summary,$stockName,$summaryName,$actor): array {
            $this->lock();
            $existing=$this->markets->rows('SELECT id,result_json FROM market_imports WHERE content_sha256=? FOR UPDATE',[$parsed['hash']]);
            if($existing) { $data=json_decode($existing[0]['result_json'],true,32,JSON_THROW_ON_ERROR); return ['data'=>$data+['duplicate_import'=>true],'status'=>200]; }
            $mapping=$this->mapping($parsed['stock']);
            $this->markets->execute('INSERT INTO market_imports (content_sha256,stock_filename,summary_filename,stock_csv,summary_csv,stock_rows,summary_rows,actor_user_id) VALUES (?,?,?,?,?,?,?,?)',
                [$parsed['hash'],$stockName,$summaryName,$stock,$summary,count($parsed['stock']),count($parsed['summary']),$actor['id']]);
            $import=(string)$this->database->connection()->lastInsertId(); $applied=0; $older=0; $same=0; $conflicts=0;
            foreach($parsed['stock'] as $row) {
                $station=$this->station($row); $match=$mapping[$row['product_key']];
                $this->markets->execute('INSERT INTO market_products (name_key,original_name,commodity_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)',[$row['product_key'],$row['mercaderia'],$match['commodity_id']]);
                $product=(string)$this->database->connection()->lastInsertId();
                $current=$this->markets->stock($station,$product,true);
                $apply=$current===null || ($row['observed_on']!==null && ($current['observed_on']===null || $row['observed_on']>$current['observed_on']));
                if(!$apply) {
                    if($row['observed_on']!==$current['observed_on']) { $older++; }
                    elseif($row['quantity']!==$current['quantity'] || $row['available']!==($current['available']===null?null:(int)$current['available']) || $row['verified']!==(int)$current['verified']) { $conflicts++; }
                    else { $same++; }
                }
                $observation=$this->observation($import,$station,$product,$row,$apply,$actor['id']);
                if($apply) {
                    $this->markets->execute('INSERT INTO market_stock (station_id,product_id,observation_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE observation_id=VALUES(observation_id),version=version+1',[$station,$product,$observation]);
                    $applied++;
                }
            }
            foreach($parsed['summary'] as $row) {
                $station=$this->station($row);
                $this->markets->execute('UPDATE market_stations SET summary_count=?,summary_source=?,summary_note=?,summary_observed_on=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=? AND (summary_observed_on IS NULL OR summary_observed_on<?)',
                    [$row['confirmed_count'],$row['fuente'],$row['observacion']?:null,$row['observed_on'],$station,$row['observed_on']]);
            }
            $data=['import_id'=>$import,'stock_rows'=>count($parsed['stock']),'summary_rows'=>count($parsed['summary']),'applied'=>$applied,'older_ignored'=>$older,'unchanged'=>$same,'same_date_conflicts'=>$conflicts];
            $this->markets->execute('UPDATE market_imports SET result_json=? WHERE id=?',[json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$import]);
            (new PlanningRepository($this->database))->audit('market_import',$import,'import',null,$data,$actor['id'],'Importación de mercados validada: '.$parsed['hash'],$operation);
            return ['data'=>$data,'status'=>200];
        });
    }
    public function edit(array $actor,int $authVersion,string $key,string $kind,string $id,array $input): array
    {
        $allowed=match($kind) { 'station'=>['version','economy','max_pad','observed_on','source','reason'], 'product'=>['version','commodity_id','reason'], 'stock'=>['version','station_id','quantity','available','verified','observed_on','source','reason'] };
        Input::fields($input,$allowed); $version=Input::version($input); $reason=Input::text($input,'reason',1000,true,true);
        if($version==='4294967295') { throw new HttpError(409,'version_exhausted','Se alcanzó el límite de versiones.'); }
        $data=['id'=>$id,'version'=>$version,'reason'=>$reason];
        if($kind==='product') { $data['commodity_id']=($input['commodity_id']??null)===null || $input['commodity_id']==='' ? null : Request::positiveId($input['commodity_id']); }
        else {
            $data['observed_on']=MarketCsv::date(Input::text($input,'observed_on',10));
            $data['source']=Input::text($input,'source',1000,true);
            if($kind==='station') {
                $data['economy']=Input::text($input,'economy',120); $data['max_pad']=Input::text($input,'max_pad',1);
                if(!in_array($data['max_pad'],[null,'S','M','L'],true)) { throw new HttpError(422,'validation','Pad máximo inválido.'); }
                if($data['observed_on']===null) { throw new HttpError(422,'validation','Indicá la fecha de la información de la estación.'); }
            } else {
                $data['station_id']=Request::positiveId($input['station_id']??null);
                $data['quantity']=($input['quantity']??null)===null || $input['quantity']==='' ? null : Input::quantity(['required_quantity'=>$input['quantity']]);
                foreach(['available','verified'] as $field) {
                    $value=$input[$field]??null;
                    if(!is_bool($value) && !($field==='available' && $value===null)) { throw new HttpError(422,'validation','Disponibilidad/verificación inválida.'); }
                    $data[$field]=$value===null?null:(int)$value;
                }
                if($data['available']===0 && $data['quantity']!==null && $data['quantity']!=='0') { throw new HttpError(422,'validation','Stock positivo incompatible con disponibilidad NO.'); }
            }
        }
        return (new MutationService($this->database))->run($actor,$authVersion,['admin','viewer'],'markets.'.$kind,$key,$data,function(string $operation) use($actor,$data,$kind,$id,$version,$reason): array {
            $this->lock();
            $before=match($kind) { 'station'=>$this->markets->station($id,true), 'product'=>$this->markets->product($id,true), 'stock'=>$this->markets->stock($data['station_id'],$id,true)??throw new HttpError(404,'not_found','Existencias no encontradas.') };
            if($before['version']!==$version) { throw new HttpError(409,'stale_version','Los datos cambiaron. Recargá antes de guardar.'); }
            if($kind==='product') {
                if($data['commodity_id']!==null) { $commodity=(new CommodityRepository($this->database))->find($data['commodity_id']); if(!$commodity['active']) { throw new HttpError(409,'inactive_commodity','El material está inactivo.'); } }
                $this->markets->execute('UPDATE market_products SET commodity_id=?,version=version+1 WHERE id=?',[$data['commodity_id'],$id]);
                $after=$this->markets->product($id);
            } elseif($kind==='station') {
                if($before['metadata_observed_on']!==null && $data['observed_on']<$before['metadata_observed_on']) { throw new HttpError(409,'older_observation','La fecha es anterior a los datos guardados.'); }
                $this->markets->execute('UPDATE market_stations SET economy=?,max_pad=?,metadata_observed_on=?,metadata_source=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?',[$data['economy'],$data['max_pad'],$data['observed_on'],$data['source'],$id]);
                $after=$this->markets->station($id);
            } else {
                if($before['observed_on']!==null && ($data['observed_on']===null || $data['observed_on']<$before['observed_on'])) { throw new HttpError(409,'older_observation','La fecha es anterior a las existencias guardadas.'); }
                $row=['mercaderia'=>$before['original_name'],'categoria'=>$before['category'],'quantity'=>$data['quantity'],'available'=>$data['available'],'verified'=>$data['verified'],'observed_on'=>$data['observed_on'],'fuente'=>$data['source'],'verificacion'=>$data['verified']?'Confirmado manualmente':'Sin verificar'];
                $observation=$this->observation(null,$data['station_id'],$id,$row,true,$actor['id']);
                $this->markets->execute('UPDATE market_stock SET observation_id=?,version=version+1 WHERE station_id=? AND product_id=?',[$observation,$data['station_id'],$id]);
                $after=$this->markets->stock($data['station_id'],$id); $id=$observation;
            }
            (new PlanningRepository($this->database))->audit('market_'.$kind,$id,'update',$before,$after,$actor['id'],$reason,$operation);
            return ['data'=>$after,'status'=>200];
        });
    }
}
