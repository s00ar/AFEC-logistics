<?php
declare(strict_types=1);
namespace Afec\Repositories;

use Afec\Database;
use Afec\Http\HttpError;
use Afec\Http\Request;
use Afec\Validation\PlanningInput as Input;

final class MarketRepository
{
    public function __construct(private readonly Database $database, private readonly int $staleDays = 7) {}
    public function rows(string $sql, array $params = []): array
    {
        $q = $this->database->connection()->prepare($sql); $q->execute($params);
        return $q->fetchAll();
    }
    public function execute(string $sql, array $params = []): void
    {
        $q = $this->database->connection()->prepare($sql); $q->execute($params);
    }
    public function ready(): bool
    {
        $rows = $this->rows("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('market_imports','market_stations','market_products','market_observations','market_stock')");
        return (int)$rows[0]['n'] === 5 && count($this->rows("SELECT version FROM schema_migrations WHERE version='009_system_markets.sql' AND status='applied'")) === 1;
    }
    public static function filters(array $input): array
    {
        $result = [];
        foreach (['q'=>120,'system'=>255,'economy'=>120] as $field=>$max) { $result[$field] = Input::text($input, $field, $max) ?? ''; }
        foreach (['station_id','commodity_id'] as $field) { $result[$field] = ($input[$field] ?? '') === '' ? null : Request::positiveId($input[$field]); }
        foreach (['ship_size'=>['','S','M','L'], 'status'=>['','confirmed','stale','unknown'], 'sort'=>['stock','updated','station']] as $field=>$choices) {
            $value = $input[$field] ?? ($field === 'sort' ? 'stock' : '');
            if (!is_string($value) || !in_array($value, $choices, true)) { throw new HttpError(422,'validation','Filtro de mercados no válido.'); }
            $result[$field] = $value;
        }
        $result['missing'] = Request::flag($input['missing'] ?? null);
        return $result;
    }
    public function statusSql(string $alias = 'o'): string
    {
        return "CASE WHEN $alias.verified=0 OR $alias.quantity IS NULL OR $alias.observed_on IS NULL OR $alias.available IS NULL THEN 'unknown' WHEN $alias.observed_on < DATE_SUB(UTC_DATE(), INTERVAL " . $this->staleDays . " DAY) THEN 'stale' ELSE 'confirmed' END";
    }
    public function options(): array
    {
        $stations = $this->rows('SELECT CAST(id AS CHAR) AS id,system_name,name,body_name,economy,max_pad,summary_count,summary_source,summary_note,summary_observed_on,CAST(version AS CHAR) AS version FROM market_stations ORDER BY system_name,name LIMIT 1000');
        return ['stations'=>$stations,'systems'=>array_values(array_unique(array_column($stations,'system_name'))),
            'economies'=>array_values(array_unique(array_filter(array_column($stations,'economy')))), 'stale_days'=>$this->staleDays];
    }
    public function station(string $id, bool $lock = false): array
    {
        return $this->rows('SELECT *,CAST(id AS CHAR) AS id,CAST(version AS CHAR) AS version FROM market_stations WHERE id=?' . ($lock?' FOR UPDATE':''),[$id])[0] ?? throw new HttpError(404,'not_found','Estación comercial no encontrada.');
    }
    public function product(string $id, bool $lock = false): array
    {
        return $this->rows('SELECT *,CAST(id AS CHAR) AS id,CAST(commodity_id AS CHAR) AS commodity_id,CAST(version AS CHAR) AS version FROM market_products WHERE id=?' . ($lock?' FOR UPDATE':''),[$id])[0] ?? throw new HttpError(404,'not_found','Mercancía comercial no encontrada.');
    }
    public function stock(string $station, string $product, bool $lock = false): ?array
    {
        return $this->rows('SELECT s.*,CAST(s.station_id AS CHAR) AS station_id,CAST(s.product_id AS CHAR) AS product_id,CAST(s.observation_id AS CHAR) AS observation_id,CAST(s.version AS CHAR) AS version,CAST(o.quantity AS CHAR) AS quantity,o.available,o.verified,o.observed_on,o.source,o.verification,o.original_name,o.category FROM market_stock s JOIN market_observations o ON o.id=s.observation_id WHERE s.station_id=? AND s.product_id=?' . ($lock?' FOR UPDATE':''),[$station,$product])[0] ?? null;
    }
    public function listing(array $filter, int $page = 1): array
    {
        $where=[]; $params=[];
        foreach (['system'=>'t.system_name','station_id'=>'t.id','economy'=>'t.economy','commodity_id'=>'p.commodity_id'] as $key=>$column) {
            if ($filter[$key] !== '' && $filter[$key] !== null) { $where[]="$column=?"; $params[]=$filter[$key]; }
        }
        if ($filter['ship_size'] !== '') {
            $pads = match ($filter['ship_size']) { 'L'=>"'L'", 'M'=>"'M','L'", 'S'=>"'S','M','L'" };
            $where[]="t.max_pad IN ($pads)";
        }
        $status=$this->statusSql();
        if ($filter['status'] !== '') { $where[]="($status)=?"; $params[]=$filter['status']; }
        if ($filter['q'] !== '') {
            $like='%'.strtr($filter['q'],['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
            $where[]="(p.original_name LIKE ? ESCAPE '!' OR o.original_name LIKE ? ESCAPE '!' OR c.name_es LIKE ? ESCAPE '!' OR c.name_en LIKE ? ESCAPE '!')";
            array_push($params,$like,$like,$like,$like);
        }
        $order=match($filter['sort']) { 'updated'=>'o.observed_on DESC,o.quantity DESC', 'station'=>'t.name,p.original_name', default=>"(o.available=1 AND o.quantity>0 AND ($status)='confirmed') DESC,(o.available=1 AND o.quantity>0) DESC,o.quantity DESC" };
        $sql="SELECT CAST(t.id AS CHAR) AS station_id,t.system_name,t.name AS station_name,t.body_name,t.economy,t.max_pad,CAST(t.version AS CHAR) AS station_version,
            CAST(p.id AS CHAR) AS product_id,p.original_name AS product_name,CAST(p.commodity_id AS CHAR) AS commodity_id,CAST(p.version AS CHAR) AS product_version,c.name_es AS commodity_name,
            CAST(o.quantity AS CHAR) AS quantity,o.available,o.verified,o.observed_on,o.source,o.verification,o.original_name,o.category,CAST(s.version AS CHAR) AS version,($status) AS stock_status
            FROM market_stock s JOIN market_stations t ON t.id=s.station_id JOIN market_products p ON p.id=s.product_id JOIN market_observations o ON o.id=s.observation_id LEFT JOIN commodities c ON c.id=p.commodity_id";
        $rows=$this->rows($sql . ($where?' WHERE '.implode(' AND ',$where):'') . ' ORDER BY '.$order.',t.id,p.id LIMIT 26 OFFSET '.(($page-1)*25),$params);
        $more=count($rows)>25; if($more) { array_pop($rows); }
        return ['items'=>$rows,'pagination'=>['page'=>$page,'has_more'=>$more]];
    }
    public function missing(string $system, int $page = 1): array
    {
        if ($system==='') { throw new HttpError(422,'validation','Elegí un sistema para consultar materiales sin proveedores.'); }
        $rows=$this->rows("SELECT DISTINCT CAST(c.id AS CHAR) AS commodity_id,c.name_es AS commodity_name FROM requirements r JOIN commodities c ON c.id=r.commodity_id JOIN installations i ON i.id=r.installation_id JOIN systems s ON s.id=i.system_id
            WHERE s.name=? AND s.archived_at IS NULL AND i.archived_at IS NULL AND (i.construction_status IS NULL OR i.construction_status<>'completed') AND r.active=1 AND r.required_quantity>0
            AND NOT EXISTS (SELECT 1 FROM market_stock ms JOIN market_products mp ON mp.id=ms.product_id JOIN market_observations mo ON mo.id=ms.observation_id JOIN market_stations mt ON mt.id=ms.station_id WHERE mp.commodity_id=c.id AND mt.system_name=s.name AND mo.available=1 AND mo.quantity>0)
            ORDER BY c.name_es,c.id LIMIT 26 OFFSET ".(($page-1)*25),[$system]);
        $more=count($rows)>25; if($more) { array_pop($rows); }
        return ['items'=>$rows,'pagination'=>['page'=>$page,'has_more'=>$more]];
    }
    public function imports(int $page=1): array
    {
        $rows=$this->rows('SELECT CAST(id AS CHAR) AS id,content_sha256,stock_filename,summary_filename,stock_rows,summary_rows,result_json,created_at FROM market_imports ORDER BY id DESC LIMIT 26 OFFSET '.(($page-1)*25));
        $more=count($rows)>25; if($more) { array_pop($rows); }
        foreach($rows as &$row) { $row['result']=json_decode($row['result_json']??'null',true); unset($row['result_json']); }
        return ['items'=>$rows,'pagination'=>['page'=>$page,'has_more'=>$more]];
    }
}
