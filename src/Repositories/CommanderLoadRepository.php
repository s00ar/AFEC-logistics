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
        return self::normalize($query->fetch() ?: throw new HttpError(404,'not_found','No se encontró el comandante.'));
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
        return ['commanders'=>array_map(self::normalize(...),$rows),'own'=>$own,'presets_ready'=>$ready,'has_more'=>$more,'page'=>$page];
    }

    public function amount(string $id): string
    {
        $row = $this->find($id, true);
        if (!$row['active']) { throw new HttpError(422,'validation','El comandante no está activo.'); }
        if ($row['standard_load_quantity'] === null) { throw new HttpError(409,'load_missing','Configurá primero la palada estándar de este comandante (requiere migración 008).'); }
        return $row['standard_load_quantity'];
    }
}
