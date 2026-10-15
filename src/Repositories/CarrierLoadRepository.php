<?php
declare(strict_types=1);
namespace Afec\Repositories;

use Afec\Database;

/** Cargo snapshots are append-only events in the existing audited ledger. */
final class CarrierLoadRepository
{
    public function __construct(private readonly Database $database) {}

    public function current(string $actor, string $carrier, string $commodity): ?array
    {
        $q=$this->database->connection()->prepare("SELECT changes_json FROM audit_log WHERE entity_type='carrier_load' AND actor_user_id=? AND entity_id=? AND JSON_UNQUOTE(JSON_EXTRACT(changes_json,'$.after.commodity_id'))=? ORDER BY id DESC LIMIT 1");
        $q->execute([$actor,$carrier,$commodity]);
        $json=$q->fetchColumn();
        return $json ? json_decode($json,true,32,JSON_THROW_ON_ERROR)['after'] : null;
    }

    public function listing(string $actor, string $scope): array
    {
        $db=$this->database->connection();
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ'); $db->beginTransaction();
        try {
            $loads=$this->snapshot($actor,$scope);
            $db->commit(); return $loads;
        } catch(\Throwable $error) { if($db->inTransaction()) { $db->rollBack(); } throw $error; }
    }

    private function snapshot(string $actor, string $scope): array
    {
        $q=$this->database->connection()->prepare("SELECT a.changes_json,c.name AS carrier_name,c.code AS carrier_code,m.name_es AS commodity_name FROM audit_log a JOIN carriers c ON c.id=a.entity_id JOIN commodities m ON m.id=JSON_UNQUOTE(JSON_EXTRACT(a.changes_json,'$.after.commodity_id')) WHERE a.id IN (SELECT MAX(id) FROM audit_log WHERE entity_type='carrier_load' AND actor_user_id=? GROUP BY entity_id,JSON_UNQUOTE(JSON_EXTRACT(changes_json,'$.after.commodity_id'))) ORDER BY c.name,c.id,m.name_es,m.id");
        $q->execute([$actor]); $loads=[]; $needs=[];
        $logistics=new LogisticsRepository($this->database);
        foreach($q->fetchAll() as $row) {
            $load=json_decode($row['changes_json'],true,32,JSON_THROW_ON_ERROR)['after'];
            if ($load['scope']!==$scope) { continue; }
            $load+=array_diff_key($row,['changes_json'=>true]);
            $remaining=(int)$load['quantity']; $allocations=[];
            $commodity=$load['commodity_id'];
            $needs[$commodity]??=$logistics->allDestinations($scope,$actor,$commodity);
            foreach($needs[$commodity] as &$destination) {
                if ($remaining===0) { break; }
                $amount=\Afec\Validation\LogisticsInput::compare((string)$remaining,$destination['remaining_quantity'])<=0 ? $remaining : (int)$destination['remaining_quantity'];
                if ($amount===0) { continue; }
                $allocations[]=$destination+['allocated_quantity'=>(string)$amount];
                $remaining-=$amount;
                $destination['remaining_quantity']=self::difference($destination['remaining_quantity'],$amount);
            } unset($destination);
            $load['allocations']=$allocations;
            $load['unallocated_quantity']=(string)$remaining;
            $loads[]=$load;
        }
        return $loads;
    }

    /** Requirements and aggregated deficits can be wider than a signed PHP integer. */
    private static function difference(string $value,int $amount): string
    {
        $borrow=0; $result='';
        for($index=strlen($value)-1;$index>=0;$index--) {
            $digit=(int)$value[$index]-($amount%10)-$borrow;
            $amount=intdiv($amount,10); $borrow=$digit<0?1:0;
            $result=(string)($digit<0?$digit+10:$digit).$result;
        }
        return ltrim($result,'0')?:'0';
    }
}
