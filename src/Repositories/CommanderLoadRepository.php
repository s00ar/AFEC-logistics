<?php
declare(strict_types=1);
namespace Afec\Repositories;

use Afec\Database;
use Afec\Http\HttpError;

final class CommanderLoadRepository
{
    public function __construct(private readonly Database $database) {}

    public function ready(): bool
    {
        return (bool)$this->database->connection()->query("SHOW COLUMNS FROM commanders LIKE 'standard_load_quantity'")->fetch();
    }

    public function find(string $id, bool $lock = false): array
    {
        $fields = $this->ready() ? 'standard_load_quantity,load_version' : 'NULL AS standard_load_quantity,1 AS load_version';
        $query = $this->database->connection()->prepare('SELECT id,display_name,active,' . $fields . ' FROM commanders WHERE id=?' . ($lock ? ' FOR UPDATE' : ''));
        $query->execute([$id]);
        $row = self::normalize($query->fetch() ?: throw new HttpError(404,'not_found','No se encontró el comandante.'));
        // Audit events support base loads even before the optional 008 columns are imported.
        $saved = $this->database->connection()->prepare("SELECT changes_json FROM audit_log WHERE entity_type='commander' AND entity_id=? AND action='standard_load' ORDER BY id DESC LIMIT 1");
        $saved->execute([$id]);
        if ($json = $saved->fetchColumn()) {
            $after = json_decode($json, true, 32, JSON_THROW_ON_ERROR)['after'];
            if ((int)$after['load_version'] >= (int)$row['load_version']) {
                $row['standard_load_quantity'] = $after['standard_load_quantity'];
                $row['load_version'] = $after['load_version'];
            }
        }
        return $row;
    }

    public static function normalize(array $row): array
    {
        foreach (['id','standard_load_quantity','load_version'] as $field) { if ($row[$field] !== null) { $row[$field] = (string)$row[$field]; } }
        $row['active'] = (bool)$row['active'];
        return $row;
    }

    public function options(array $actor, int $page): array
    {
        $ready = $this->ready();
        $fields = $ready ? 'standard_load_quantity,load_version' : 'NULL AS standard_load_quantity,1 AS load_version';
        $query = $this->database->connection()->prepare('SELECT id,display_name,active,' . $fields . ' FROM commanders WHERE active=1'
            . ($actor['role'] === 'viewer' ? ' AND id=?' : '') . ' ORDER BY display_name,id LIMIT 51 OFFSET ' . (($page - 1) * 50));
        $query->execute($actor['role'] === 'viewer' ? [$actor['commander_id'] ?? '0'] : []);
        $rows = $query->fetchAll(); $more = count($rows) > 50; if ($more) { array_pop($rows); }
        $own = $actor['commander_id'] === null ? null : $this->find($actor['commander_id']);
        return ['commanders'=>array_map(fn(array $row): array => $this->find((string)$row['id']),$rows),'own'=>$own,'presets_ready'=>true,'load_columns_ready'=>$ready,'has_more'=>$more,'page'=>$page];
    }

    public function amount(string $id): string
    {
        $row = $this->find($id, true);
        if (!$row['active']) { throw new HttpError(422,'validation','El comandante no está activo.'); }
        if ($row['standard_load_quantity'] === null) { throw new HttpError(409,'load_missing','Configurá primero la palada base de este comandante.'); }
        return $row['standard_load_quantity'];
    }
}
