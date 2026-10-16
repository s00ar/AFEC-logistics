<?php
declare(strict_types=1);
namespace Afec\Repositories;

use Afec\Database;
use Afec\Http\HttpError;

final class LogisticsRepository
{
    public function __construct(private readonly Database $database) {}
    public function rows(string $sql, array $params = []): array
    {
        $q = $this->database->connection()->prepare($sql);
        $q->execute($params);
        return array_map(static function(array $row): array {
            foreach ($row as $key => $value) {
                if ($value !== null && ($key === 'id' || $key === 'version' || str_ends_with($key,'_id') || str_ends_with($key,'_quantity'))) { $row[$key] = (string)$value; }
            }
            return $row;
        },$q->fetchAll());
    }
    public function execute(string $sql, array $params): void
    {
        $q=$this->database->connection()->prepare($sql); $q->execute($params);
    }
    private function page(array $rows, int $page): array
    {
        $more=count($rows)>25; if($more) { array_pop($rows); }
        return ['items'=>$rows,'pagination'=>['page'=>$page,'has_more'=>$more]];
    }
    public function source(string $id, bool $lock = false): array
    {
        return $this->rows('SELECT id,commodity_id,system_name,facility_name,facility_type,notes,active,version,created_by_user_id,updated_by_user_id,updated_at FROM commodity_sources WHERE id=?' . ($lock?' FOR UPDATE':''),[$id])[0] ?? throw new HttpError(404,'not_found','No se encontró el lugar de abastecimiento.');
    }
    public function sources(string $commodity, int $page=1, bool $inactive=false): array
    {
        return $this->page($this->rows('SELECT s.*,u.display_name AS author FROM commodity_sources s JOIN users u ON u.id=s.updated_by_user_id WHERE s.commodity_id=? ' . ($inactive?'':'AND s.active=1') . ' ORDER BY s.active DESC,s.system_name,s.facility_name,s.id LIMIT 26 OFFSET ' . (($page-1)*25),[$commodity]),$page);
    }
    public static function listKey(string $scope, string $actor): string { return $scope==='fleet'?'fleet':'personal:'.$actor; }
    public function entry(string $key, string $installation, bool $lock=false): ?array
    {
        return $this->rows('SELECT * FROM wishlist_entries WHERE list_key=? AND installation_id=?' . ($lock?' FOR UPDATE':''),[$key,$installation])[0] ?? null;
    }
    private function needsSql(): string
    {
        return "SELECT r.installation_id,r.commodity_id,i.name AS installation_name,i.system_id,s.name AS system_name,c.name_es AS commodity_name,
            GREATEST(CAST(r.required_quantity AS DECIMAL(65,0))-COALESCE(m.delivered,0),0) AS remaining_quantity
            FROM wishlist_entries w JOIN installations i ON i.id=w.installation_id JOIN systems s ON s.id=i.system_id
            JOIN requirements r ON r.installation_id=i.id JOIN commodities c ON c.id=r.commodity_id
            LEFT JOIN (SELECT installation_id,commodity_id,SUM(delta_quantity) AS delivered FROM movements GROUP BY installation_id,commodity_id) m ON m.installation_id=r.installation_id AND m.commodity_id=r.commodity_id
            WHERE w.list_key=? AND w.active=1 AND r.active=1 AND i.archived_at IS NULL AND s.archived_at IS NULL AND (i.construction_status IS NULL OR i.construction_status<>'completed')";
    }
    private function completedMaterials(string $key, int $page): array
    {
        return $this->page($this->rows('SELECT r.commodity_id,c.name_es AS commodity_name,
            SUM(CAST(r.required_quantity AS DECIMAL(65,0))) AS required_quantity,
            SUM(COALESCE(m.delivered,0)) AS delivered_quantity,
            SUM(GREATEST(COALESCE(m.delivered,0)-CAST(r.required_quantity AS DECIMAL(65,0)),0)) AS surplus_quantity
            FROM wishlist_entries w JOIN installations i ON i.id=w.installation_id JOIN systems s ON s.id=i.system_id
            JOIN requirements r ON r.installation_id=i.id JOIN commodities c ON c.id=r.commodity_id
            LEFT JOIN (SELECT installation_id,commodity_id,SUM(delta_quantity) AS delivered FROM movements GROUP BY installation_id,commodity_id) m ON m.installation_id=r.installation_id AND m.commodity_id=r.commodity_id
            WHERE w.list_key=? AND w.active=1 AND r.active=1 AND r.required_quantity>0 AND i.archived_at IS NULL AND s.archived_at IS NULL
            GROUP BY r.commodity_id,c.name_es
            HAVING SUM(GREATEST(CAST(r.required_quantity AS DECIMAL(65,0))-COALESCE(m.delivered,0),0))=0
            ORDER BY c.name_es,r.commodity_id LIMIT 26 OFFSET ' . (($page-1)*25),[$key]),$page);
    }
    public function wishlist(string $scope, string $actor, int $page=1, int $selectionPage=1, int $completedPage=1): array
    {
        $db=$this->database->connection(); $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $db->beginTransaction();
        try {
            $key=self::listKey($scope,$actor);
            $commodities=$this->page($this->rows('SELECT n.commodity_id,n.commodity_name,SUM(n.remaining_quantity) AS remaining_quantity,COUNT(*) AS destination_count,
                (SELECT COUNT(*) FROM commodity_sources cs WHERE cs.commodity_id=n.commodity_id AND cs.active=1) AS source_count
                FROM (' . $this->needsSql() . ') n WHERE n.remaining_quantity>0 GROUP BY n.commodity_id,n.commodity_name ORDER BY n.commodity_name,n.commodity_id LIMIT 26 OFFSET ' . (($page-1)*25),[$key]),$page);
            foreach($commodities['items'] as &$item) { $item['destinations']=$this->destinations($scope,$actor,$item['commodity_id']); } unset($item);
            $selected=$this->page($this->rows('SELECT w.installation_id,w.version,i.name AS installation_name,i.system_id,s.name AS system_name,i.archived_at,s.archived_at AS system_archived_at,i.construction_status
                FROM wishlist_entries w JOIN installations i ON i.id=w.installation_id JOIN systems s ON s.id=i.system_id WHERE w.list_key=? AND w.active=1 ORDER BY s.name,i.name,i.id LIMIT 26 OFFSET ' . (($selectionPage-1)*25),[$key]),$selectionPage);
            $completed=$this->completedMaterials($key,$completedPage);
            $db->commit(); return ['scope'=>$scope,'commodities'=>$commodities,'selected'=>$selected,'completed'=>$completed];
        } catch(\Throwable $error) { if($db->inTransaction()) { $db->rollBack(); } throw $error; }
    }
    public function destinations(string $scope,string $actor,string $commodity,int $page=1): array
    {
        return $this->page($this->rows('SELECT n.* FROM (' . $this->needsSql() . ') n WHERE n.commodity_id=? AND n.remaining_quantity>0 ORDER BY n.system_name,n.installation_name,n.installation_id LIMIT 26 OFFSET ' . (($page-1)*25),[self::listKey($scope,$actor),$commodity]),$page);
    }
    public function allDestinations(string $scope, string $actor, string $commodity): array
    {
        return $this->rows('SELECT n.* FROM (' . $this->needsSql() . ') n WHERE n.commodity_id=? AND n.remaining_quantity>0 ORDER BY n.system_name,n.installation_name,n.installation_id', [self::listKey($scope,$actor),$commodity]);
    }
    public function movement(string $id,bool $lock=false): array
    {
        return $this->rows('SELECT * FROM movements WHERE id=?' . ($lock?' FOR UPDATE':''),[$id])[0] ?? throw new HttpError(404,'not_found','No se encontró el movimiento.');
    }
    public function reversible(string $id): string
    {
        return $this->rows('SELECT CAST(m.delta_quantity+COALESCE((SELECT SUM(r.delta_quantity) FROM movements r WHERE r.reverses_movement_id=m.id),0) AS CHAR) AS available_quantity FROM movements m WHERE m.id=?',[$id])[0]['available_quantity'];
    }
    public function movements(string $installation,array $actor,int $page=1): array
    {
        $listing=$this->page($this->rows('SELECT m.id,m.installation_id,m.commodity_id,m.created_by_user_id,m.commander_id,m.carrier_id,m.delta_quantity,m.movement_type,m.reverses_movement_id,m.occurred_at,m.recorded_at,m.reason,c.name_es AS commodity_name,u.display_name AS author,cmd.display_name AS commander_name,car.name AS carrier_name,
            m.delta_quantity+COALESCE((SELECT SUM(r.delta_quantity) FROM movements r WHERE r.reverses_movement_id=m.id),0) AS available_quantity
            FROM movements m JOIN commodities c ON c.id=m.commodity_id JOIN users u ON u.id=m.created_by_user_id LEFT JOIN commanders cmd ON cmd.id=m.commander_id LEFT JOIN carriers car ON car.id=m.carrier_id
            WHERE m.installation_id=? ORDER BY m.id DESC LIMIT 26 OFFSET ' . (($page-1)*25),[$installation]),$page);
        foreach($listing['items'] as &$item) {
            $item['can_reverse']= $item['delta_quantity'][0]!=='-' && $item['available_quantity']!=='0';
        } unset($item);
        return $listing;
    }
    public function ownCommander(array $actor): string
    {
        $row=$this->rows('SELECT commander_id,display_name,version FROM users WHERE id=? FOR UPDATE',[$actor['id']])[0];
        if($row['commander_id']!==null) { return $row['commander_id']; }
        if($row['version']==='4294967295') { throw new HttpError(409,'version_exhausted','Se alcanzó el límite de versiones de cuenta.'); }
        $id=$this->newCommander($row['display_name']);
        $this->execute('UPDATE users SET commander_id=?,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?',[$id,$actor['id']]);
        return $id;
    }
    public function newCommander(string $name): string
    {
        $this->execute('INSERT INTO commanders (display_name,normalized_name) VALUES (?,?)',[$name,mb_strtolower($name)]);
        return $this->database->connection()->lastInsertId();
    }
    public function choices(): array
    {
        $load=(new CommanderLoadRepository($this->database))->ready()?'standard_load_quantity':'NULL AS standard_load_quantity';
        $commanders=$this->rows('SELECT id,display_name,'.$load.' FROM commanders WHERE active=1 ORDER BY display_name,id LIMIT 101');
        $loads=new CommanderLoadRepository($this->database);
        foreach($commanders as &$commander) { $commander['standard_load_quantity']=$loads->find($commander['id'])['standard_load_quantity']; } unset($commander);
        return ['commanders'=>$commanders, 'carriers'=>$this->rows('SELECT id,name,code FROM carriers WHERE active=1 ORDER BY name,id LIMIT 101')];
    }
}
