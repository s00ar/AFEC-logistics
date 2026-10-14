<?php
declare(strict_types=1);
namespace Afec\Services;

use Afec\Http\HttpError;
use Afec\Http\Request;
use Afec\Repositories\PlanningRepository;
use Afec\Repositories\RecipeRepository;
use Afec\Validation\PlanningInput as Input;

final class RecipeService
{
    public function __construct(private readonly RecipeRepository $recipes, private readonly PlanningRepository $planning, private readonly MutationService $mutations) {}

    public function save(array $actor, int $authVersion, string $key, ?string $id, array $input): array
    {
        Input::fields($input, ['name', 'type', 'tier', 'source_note', 'source_url', 'materials', 'reason', ...($id === null ? [] : ['version'])]);
        $url = Input::text($input, 'source_url', 1000);
        if ($url !== null && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true))) {
            throw new HttpError(422, 'validation', 'La referencia debe ser una URL HTTP o HTTPS válida.');
        }
        $data = ['name' => Input::text($input, 'name', 160, true), 'type' => Input::text($input, 'type', 80, true), 'tier' => Input::text($input, 'tier', 20),
            'source_note' => Input::text($input, 'source_note', 1000, true, true), 'source_url' => $url, 'materials' => self::materials($input['materials'] ?? null)];
        $version = $id === null ? null : Input::version($input);
        $reason = Input::text($input, 'reason', 1000, true, true);
        $scope = $id === null ? 'planning.recipe.create' : 'planning.recipe.update';
        return $this->mutations->run($actor, $authVersion, ['admin', 'operator'], $scope, $key, $data + ['id' => $id, 'version' => $version, 'reason' => $reason], function(string $operationId) use ($id, $version, $data, $reason, $actor): array {
            $before = $id === null ? null : $this->recipes->find($id, true);
            if ($before !== null) { self::version($before, $version); self::active($before); }
            foreach ($data['materials'] as $material) { $this->planning->activeCommodity($material['commodity_id']); }
            $saved = $this->recipes->save($id, $data);
            $after = $this->recipes->find($saved);
            $this->planning->audit('facility_recipe', $saved, $id === null ? 'create' : 'update', $before, $after, $actor['id'], $reason, $operationId);
            return ['data' => $after, 'status' => $id === null ? 201 : 200];
        });
    }

    public function archive(array $actor, int $authVersion, string $key, string $id, array $input, bool $restore): array
    {
        Input::fields($input, ['version', 'reason', 'confirmed']);
        if (!$restore) { Input::confirmation($input); }
        $version = Input::version($input);
        $reason = Input::text($input, 'reason', 1000, true, true);
        $scope = 'planning.recipe.' . ($restore ? 'restore' : 'archive');
        return $this->mutations->run($actor, $authVersion, ['admin', 'operator'], $scope, $key, ['id' => $id, 'version' => $version, 'reason' => $reason, 'confirmed' => !$restore], function(string $operationId) use ($id, $version, $reason, $restore, $actor): array {
            $before = $this->recipes->find($id, true);
            self::version($before, $version);
            if (($before['archived_at'] === null) === $restore) { throw new HttpError(409, 'archive_state', 'El estado de la receta cambió. Recargá los datos.'); }
            $this->recipes->archive($id, $restore);
            $after = $this->recipes->find($id);
            $this->planning->audit('facility_recipe', $id, $restore ? 'restore' : 'archive', $before, $after, $actor['id'], $reason, $operationId);
            return ['data' => $after, 'status' => 200];
        });
    }

    private static function materials(mixed $rows): array
    {
        if (!is_array($rows) || !array_is_list($rows) || count($rows) < 1 || count($rows) > 100) { throw new HttpError(422, 'validation', 'La receta debe contener entre 1 y 100 materiales.'); }
        $result = []; $seen = [];
        foreach ($rows as $row) {
            if ($row instanceof \stdClass) { $row = (array)$row; }
            if (!is_array($row)) { throw new HttpError(422, 'validation', 'Material de receta inválido.'); }
            Input::fields($row, ['commodity_id', 'required_quantity']);
            $id = Request::positiveId($row['commodity_id'] ?? null);
            $quantity = Input::quantity($row);
            if ($quantity === '0' || isset($seen[$id])) { throw new HttpError(422, 'validation', 'Usá cantidades positivas y un único renglón por material.'); }
            $seen[$id] = true;
            $result[] = ['commodity_id' => $id, 'required_quantity' => $quantity];
        }
        // Consistent numeric ID lock order, without converting BIGINT IDs to floats.
        usort($result, static fn(array $a, array $b): int => strlen($a['commodity_id']) <=> strlen($b['commodity_id']) ?: strcmp($a['commodity_id'], $b['commodity_id']));
        return $result;
    }

    public static function version(array $row, string $expected): void
    {
        if ($row['version'] !== $expected) { throw new HttpError(409, 'stale_version', 'La receta cambió. Recargá y revisá sus materiales antes de guardar.'); }
        if ($expected === '4294967295') { throw new HttpError(409, 'version_exhausted', 'Se alcanzó el límite técnico de versiones.'); }
    }

    public static function active(array $row): void
    {
        if ($row['archived_at'] !== null) { throw new HttpError(409, 'recipe_archived', 'La receta está eliminada de la selección. Restaurala o elegí otra.'); }
        if ($row['materials'] === []) { throw new HttpError(422, 'empty_recipe', 'La receta no tiene materiales.'); }
    }
}
