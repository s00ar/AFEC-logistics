<?php
declare(strict_types=1);
namespace Afec\Services;

use Afec\Database;
use Afec\Http\HttpError;
use Afec\Repositories\CommodityRepository;
use Afec\Repositories\PlanningRepository;
use Afec\Validation\PlanningInput as Input;

final class CommodityService
{
    public function __construct(private readonly Database $database) {}

    public function write(array $actor, int $authVersion, string $key, string $action, ?string $id, array $input): array
    {
        Input::fields($input, in_array($action, ['create','update'], true)
            ? ['code','name_es','name_en','aliases','preserve_previous','version','reason'] : ['version','reason','confirmed']);
        $data = ['reason' => Input::text($input, 'reason', 1000, $action !== 'create', true) ?? 'Alta de mercancía'];
        if ($action !== 'create') {
            $data['id'] = $id; $data['version'] = $input['version'] ?? null;
            if (!is_string($data['version']) || !preg_match('/^[a-f0-9]{64}$/D', $data['version'])) {
                throw new HttpError(422, 'validation', 'Recargá la mercancía antes de modificarla.');
            }
        }
        if (in_array($action, ['create','update'], true)) {
            $data['preserve_previous'] = $input['preserve_previous'] ?? true;
            if (!is_bool($data['preserve_previous'])) { throw new HttpError(422, 'validation', 'Indicá si se conservan las identidades anteriores.'); }
            foreach (['code' => 120, 'name_es' => 255, 'name_en' => 255] as $field => $maximum) {
                $data[$field] = Input::text($input, $field, $maximum, $field !== 'name_en');
                if ($data[$field] !== null && RecipeCatalogue::normalize($data[$field]) === '') {
                    throw new HttpError(422, 'validation', 'El código y los nombres deben contener letras o números.');
                }
            }
            $aliases = $input['aliases'] ?? [];
            if (!is_array($aliases) || !array_is_list($aliases) || count($aliases) > 30) {
                throw new HttpError(422, 'validation', 'Usá una lista de hasta 30 nombres o códigos alternativos.');
            }
            $data['aliases'] = []; $seen = [];
            foreach ($aliases as $alias) {
                $alias = Input::text(['alias' => $alias], 'alias', 255, true);
                $normalized = RecipeCatalogue::normalize($alias);
                if ($normalized === '' || isset($seen[$normalized])) { throw new HttpError(422, 'validation', 'Los alias deben ser distintos y contener letras o números.'); }
                $seen[$normalized] = true; $data['aliases'][] = $alias;
            }
        }
        if ($action === 'deactivate') { Input::confirmation($input); }
        return (new MutationService($this->database))->run($actor, $authVersion, ['admin', 'viewer'], 'planning.commodity.' . $action, $key, $data,
            function(string $operationId) use ($actor, $action, $id, $data): array {
                $db = $this->database->connection();
                // Same mutex as catalogue imports: aliases and codes form one shared namespace.
                $lock = $db->query("SELECT name FROM permission_locks WHERE name = 'recipe_catalogue' FOR UPDATE")->fetch();
                if (!$lock) { throw new HttpError(409, 'schema_missing', 'Debe aplicarse el esquema de catálogo existente.'); }
                $repository = new CommodityRepository($this->database);
                $rows = $repository->rows(true); $before = null;
                if ($id !== null) {
                    foreach ($rows as $row) { if ($row['id'] === $id) { $before = $row; break; } }
                    if ($before === null) { throw new HttpError(404, 'not_found', 'No se encontró la mercancía.'); }
                    if (!hash_equals($before['version'], $data['version'])) { throw new HttpError(409, 'stale_version', 'La mercancía cambió. Actualizá los datos.'); }
                }
                $ready = $repository->aliasesReady();
                if (in_array($action, ['create','update'], true)) {
                    $aliases = $data['aliases'];
                    // Preserve old identity when it is changed, so imports still resolve it.
                    if ($before !== null && $data['preserve_previous']) {
                        foreach (['code','name_es','name_en'] as $field) {
                            if ($before[$field] !== null && $before[$field] !== $data[$field]) { $aliases[] = $before[$field]; }
                        }
                    }
                    $canonical = array_fill_keys(array_map(RecipeCatalogue::normalize(...), array_filter([$data['code'],$data['name_es'],$data['name_en']], static fn($value): bool => $value !== null && $value !== '')), true);
                    $unique = [];
                    foreach ($aliases as $alias) {
                        $normalized = RecipeCatalogue::normalize($alias);
                        if (!isset($canonical[$normalized])) { $unique[$normalized] = $alias; }
                    }
                    $aliases = array_values($unique);
                    if (count($aliases) > 30) { throw new HttpError(422, 'validation', 'Hay más de 30 alias al conservar los nombres anteriores. Retirá alias que ya no correspondan.'); }
                    if (!$ready && $aliases) { throw new HttpError(409, 'aliases_migration_missing', 'Importá la migración 007 para guardar equivalencias.'); }
                    $proposed = $canonical + array_fill_keys(array_keys($unique), true);
                    foreach ($rows as $row) {
                        if ($row['id'] === $id) { continue; }
                        foreach (array_filter([$row['code'],$row['name_es'],$row['name_en'],...$row['aliases']], static fn($value): bool => $value !== null && $value !== '') as $value) {
                            if (isset($proposed[RecipeCatalogue::normalize($value)])) {
                                throw new HttpError(409, 'commodity_exists', 'El nombre, código o alias ya corresponde a ' . $row['name_es'] . '. Editá esa mercancía o revisá sus equivalencias.');
                            }
                        }
                    }
                    $values = [$data['code'],$data['name_es'],$data['name_en']];
                    if ($ready) { $values[] = json_encode($aliases, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
                    if ($action === 'create') {
                        $query = $db->prepare('INSERT INTO commodities (code,name_es,name_en' . ($ready ? ',aliases_json' : '') . ') VALUES (?,?,?' . ($ready ? ',?' : '') . ')');
                        $query->execute($values); $id = $db->lastInsertId();
                    } else {
                        $query = $db->prepare('UPDATE commodities SET code=?,name_es=?,name_en=?' . ($ready ? ',aliases_json=?' : '') . ',updated_at=UTC_TIMESTAMP(6) WHERE id=?');
                        $query->execute([...$values,$id]);
                    }
                } else {
                    $active = $action === 'restore';
                    if ($before['active'] === $active) { throw new HttpError(409, 'archive_state', 'La mercancía ya tiene ese estado.'); }
                    $query = $db->prepare('UPDATE commodities SET active=?,updated_at=UTC_TIMESTAMP(6) WHERE id=?');
                    $query->execute([(int)$active,$id]);
                }
                $after = $repository->find($id);
                (new PlanningRepository($this->database))->audit('commodity',$id,$action,$before,$after,$actor['id'],$data['reason'],$operationId);
                return ['data'=>$after,'status'=>$action === 'create' ? 201 : 200];
            });
    }
}
