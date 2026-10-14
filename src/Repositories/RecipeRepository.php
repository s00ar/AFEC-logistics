<?php
declare(strict_types=1);
namespace Afec\Repositories;

use Afec\Database;
use Afec\Http\HttpError;

final class RecipeRepository
{
    public function __construct(private readonly Database $database) {}

    public function listing(int $page, bool $includeArchived): array
    {
        $offset = ($page - 1) * 25;
        $query = $this->database->connection()->prepare('SELECT CAST(r.id AS CHAR) AS id, r.name, r.type, r.tier, r.source_note, r.source_url, r.archived_at, CAST(r.version AS CHAR) AS version,
            (SELECT COUNT(*) FROM facility_recipe_materials m WHERE m.recipe_id = r.id) AS material_count
            FROM facility_recipes r ' . ($includeArchived ? '' : 'WHERE r.archived_at IS NULL') . ' ORDER BY r.name, r.id LIMIT 26 OFFSET ' . $offset);
        $query->execute();
        $rows = $query->fetchAll();
        $more = count($rows) > 25;
        if ($more) { array_pop($rows); }
        return ['items' => $rows, 'page' => $page, 'has_more' => $more];
    }

    /** Call within a transaction: the parent lock protects its complete recipe. */
    public function find(string $id, bool $lock = false): array
    {
        $query = $this->database->connection()->prepare('SELECT CAST(id AS CHAR) AS id, name, type, tier, source_note, source_url, archived_at, CAST(version AS CHAR) AS version FROM facility_recipes WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''));
        $query->execute([$id]);
        $row = $query->fetch() ?: throw new HttpError(404, 'not_found', 'No se encontró la receta.');
        $query = $this->database->connection()->prepare('SELECT CAST(m.commodity_id AS CHAR) AS commodity_id, CAST(m.required_quantity AS CHAR) AS required_quantity, c.name_es AS commodity_name, c.active AS commodity_active FROM facility_recipe_materials m JOIN commodities c ON c.id = m.commodity_id WHERE m.recipe_id = ? ORDER BY m.commodity_id');
        $query->execute([$id]);
        $row['materials'] = $query->fetchAll();
        return $row;
    }

    public function snapshot(string $id): array
    {
        $db = $this->database->connection();
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->beginTransaction();
        try { $row = $this->find($id); $db->commit(); return $row; }
        catch (\Throwable $error) { if ($db->inTransaction()) { $db->rollBack(); } throw $error; }
    }

    public function save(?string $id, array $data): string
    {
        $db = $this->database->connection();
        $parameters = [$data['name'], $data['type'], $data['tier'], $data['source_note'], $data['source_url']];
        if ($id === null) {
            $query = $db->prepare('INSERT INTO facility_recipes (name, type, tier, source_note, source_url) VALUES (?, ?, ?, ?, ?)');
            $query->execute($parameters);
            $id = $db->lastInsertId();
        } else {
            $query = $db->prepare('UPDATE facility_recipes SET name = ?, type = ?, tier = ?, source_note = ?, source_url = ?, version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE id = ?');
            $query->execute([...$parameters, $id]);
            $query = $db->prepare('DELETE FROM facility_recipe_materials WHERE recipe_id = ?');
            $query->execute([$id]);
        }
        $query = $db->prepare('INSERT INTO facility_recipe_materials (recipe_id, commodity_id, required_quantity) VALUES (?, ?, ?)');
        foreach ($data['materials'] as $material) { $query->execute([$id, $material['commodity_id'], $material['required_quantity']]); }
        return $id;
    }

    public function archive(string $id, bool $restore): void
    {
        $query = $this->database->connection()->prepare('UPDATE facility_recipes SET archived_at = ' . ($restore ? 'NULL' : 'UTC_TIMESTAMP(6)') . ', version = version + 1, updated_at = UTC_TIMESTAMP(6) WHERE id = ?');
        $query->execute([$id]);
    }
}
