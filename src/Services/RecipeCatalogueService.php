<?php
declare(strict_types=1);
namespace Afec\Services;

use Afec\Database;
use Afec\Http\HttpError;
use Afec\Repositories\PlanningRepository;
use Afec\Repositories\RecipeRepository;

final class RecipeCatalogueService
{
    public function __construct(private readonly Database $database, private readonly RecipeCatalogue $catalogue) {}

    public function preview(): array
    {
        $db = $this->database->connection();
        $existing = $db->query('SELECT source_key FROM facility_recipes WHERE source_key IS NOT NULL')->fetchAll(\PDO::FETCH_COLUMN);
        $keys = array_flip($existing);
        $new = count(array_filter($this->catalogue->data['recipes'], static fn(array $row): bool => !isset($keys[$row['source_key']])));
        return $this->catalogue->summary() + ['new_recipes' => $new, 'existing_recipes_preserved' => count($this->catalogue->data['recipes']) - $new];
    }

    public function import(array $actor, int $authVersion, string $key): array
    {
        return (new MutationService($this->database))->run($actor, $authVersion, ['admin'], 'planning.catalogue.import', $key,
            ['snapshot_sha256' => $this->catalogue->hash], function(string $operationId) use ($actor): array {
                $db = $this->database->connection();
                $query = $db->prepare("SELECT name FROM permission_locks WHERE name = 'recipe_catalogue' FOR UPDATE"); $query->execute();
                if (!$query->fetch()) { throw new \RuntimeException('Catalogue migration is missing.'); }
                $planning = new PlanningRepository($this->database); $recipes = new RecipeRepository($this->database);
                $query = $db->prepare('SELECT CAST(id AS CHAR) AS id, source_key FROM facility_recipes WHERE source_key IS NOT NULL'); $query->execute();
                $present = array_column($query->fetchAll(), 'id', 'source_key');
                $pending = array_values(array_filter($this->catalogue->data['recipes'], static fn(array $row): bool => !isset($present[$row['source_key']])));
                $used = [];
                foreach ($pending as $recipe) { foreach ($recipe['materials'] as $material) { $used[$material['code']] = true; } }
                $existing = $db->query('SELECT CAST(id AS CHAR) AS id, code, name_es, name_en, active FROM commodities ORDER BY id FOR UPDATE')->fetchAll();
                $mapped = []; $createdCommodities = 0;
                foreach ($this->catalogue->data['commodities'] as $commodity) {
                    if (!isset($used[$commodity['code']])) { continue; }
                    $aliases = array_fill_keys(array_map(RecipeCatalogue::normalize(...), [...$commodity['aliases'], $commodity['code'], $commodity['name_es'], $commodity['name_en']]), true);
                    $matches = [];
                    foreach ($existing as $row) {
                        foreach (['code','name_es','name_en'] as $field) {
                            if ($row[$field] !== null && isset($aliases[RecipeCatalogue::normalize($row[$field])])) { $matches[$row['id']] = $row; }
                        }
                    }
                    if (count($matches) > 1) { throw new HttpError(409, 'ambiguous_commodity', 'Hay varias mercancías equivalentes a ' . $commodity['name_en'] . '. Revisá el catálogo antes de cargar recetas.'); }
                    if ($matches) {
                        $row = reset($matches);
                        if (!(bool)$row['active']) { throw new HttpError(409, 'inactive_commodity', 'La mercancía ' . $commodity['name_en'] . ' está inactiva. Revisala antes de cargar recetas.'); }
                    } else {
                        $row = $planning->insertCommodity(array_intersect_key($commodity, array_flip(['code','name_es','name_en'])));
                        $existing[] = $row + ['active' => 1]; $createdCommodities++;
                        $planning->audit('commodity', $row['id'], 'create', null, $row, $actor['id'], 'Catálogo inicial: ' . $this->catalogue->hash, $operationId);
                    }
                    $mapped[$commodity['code']] = $row['id'];
                }
                $origin = $db->prepare('UPDATE facility_recipes SET source_kind = ?, source_key = ?, source_snapshot_sha256 = ? WHERE id = ?');
                foreach ($pending as $recipe) {
                    $data = array_intersect_key($recipe, array_flip(['name','type','tier','source_note','source_url']));
                    $data['materials'] = array_map(static fn(array $m): array => ['commodity_id' => $mapped[$m['code']], 'required_quantity' => $m['required_quantity']], $recipe['materials']);
                    $id = $recipes->save(null, $data);
                    $origin->execute([$recipe['source_kind'], $recipe['source_key'], $this->catalogue->hash, $id]);
                    $after = $recipes->find($id);
                    $planning->audit('facility_recipe', $id, 'create', null, $after, $actor['id'], 'Carga inicial: ' . $recipe['source_locator'], $operationId);
                }
                return ['data' => ['snapshot_sha256' => $this->catalogue->hash, 'created_recipes' => count($pending),
                    'created_commodities' => $createdCommodities, 'preserved_recipes' => count($this->catalogue->data['recipes']) - count($pending)], 'status' => 200];
            });
    }
}
