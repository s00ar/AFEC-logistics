<?php
declare(strict_types=1);
namespace Afec\Services;

use Afec\Http\HttpError;
use Afec\Repositories\PlanningRepository;
use Afec\Repositories\RecipeRepository;
use Afec\Validation\PlanningInput as Input;
use PDOException;

final class PlanningService
{
    public function __construct(private readonly PlanningRepository $repository, private readonly MutationService $mutations, private readonly RecipeRepository $recipes) {}

    public function createSystem(array $actor, int $authVersion, string $key, array $input): array
    {
        Input::fields($input, ['name', 'description', 'reason']);
        $data = ['name' => Input::text($input, 'name', 255, true), 'description' => Input::text($input, 'description', 4000, false, true)];
        $reason = Input::text($input, 'reason', 1000, false, true) ?? 'Alta de sistema';
        return $this->mutations->run($actor, $authVersion, ['admin', 'viewer'], 'planning.system.create', $key, $data + ['reason' => $reason], function(string $operationId) use ($data, $actor, $reason): array {
            $id = $this->repository->insertSystem($data);
            $after = $this->repository->system($id);
            $this->repository->audit('system', $id, 'create', null, $after, $actor['id'], $reason, $operationId);
            return ['data' => $after, 'status' => 201];
        });
    }

    public function updateSystem(array $actor, int $authVersion, string $key, string $id, array $input): array
    {
        Input::fields($input, ['name', 'description', 'version', 'reason']);
        $data = ['name' => Input::text($input, 'name', 255, true), 'description' => Input::text($input, 'description', 4000, false, true)];
        $version = Input::version($input);
        $reason = Input::text($input, 'reason', 1000, false, true) ?? 'Actualización de planificación';
        return $this->mutations->run($actor, $authVersion, ['admin', 'viewer'], 'planning.system.update', $key, $data + ['id' => $id, 'version' => $version, 'reason' => $reason], function(string $operationId) use ($id, $data, $version, $actor, $reason): array {
            $before = $this->repository->system($id, true);
            self::version($before, $version);
            $this->repository->updateSystem($id, $data);
            $after = $this->repository->system($id);
            $this->repository->audit('system', $id, 'update', $before, $after, $actor['id'], $reason, $operationId);
            return ['data' => $after, 'status' => 200];
        });
    }

    private static function installationInput(array $input, bool $edit): array
    {
        $fields = ['name', 'body_name', 'location_label', 'tier', 'type', 'state', 'sort_order', 'reason'];
        Input::fields($input, $edit ? [...$fields, 'version'] : [...$fields, 'facility_recipe_id', 'facility_recipe_version']);
        return [
            'name' => Input::text($input, 'name', 255, true),
            'body_name' => Input::text($input, 'body_name', 255),
            'location_label' => Input::text($input, 'location_label', 255),
            'tier' => Input::text($input, 'tier', 20),
            'type' => Input::text($input, 'type', 80),
            'state' => Input::text($input, 'state', 60) ?? 'planned',
            'sort_order' => Input::order($input),
        ];
    }

    public function createInstallation(array $actor, int $authVersion, string $key, string $systemId, array $input): array
    {
        $data = self::installationInput($input, false);
        $recipeId = ($input['facility_recipe_id'] ?? '') === '' || ($input['facility_recipe_id'] ?? null) === null ? null : \Afec\Http\Request::positiveId($input['facility_recipe_id']);
        $recipeVersion = $recipeId === null ? null : Input::version(['version' => $input['facility_recipe_version'] ?? null]);
        if ($recipeId === null && !in_array($input['facility_recipe_version'] ?? null, [null, ''], true)) { throw new HttpError(422, 'validation', 'Seleccioná la receta correspondiente a esa versión.'); }
        $reason = Input::text($input, 'reason', 1000, false, true) ?? 'Alta de instalación';
        return $this->mutations->run($actor, $authVersion, ['admin', 'viewer'], 'planning.installation.create', $key, $data + ['system_id' => $systemId, 'reason' => $reason, 'facility_recipe_id' => $recipeId, 'facility_recipe_version' => $recipeVersion], function(string $operationId) use ($systemId, $data, $recipeId, $recipeVersion, $actor, $reason): array {
            self::active($this->repository->system($systemId, true));
            $recipe = $recipeId === null ? null : $this->recipes->find($recipeId, true);
            if ($recipe !== null) {
                RecipeService::version($recipe, $recipeVersion);
                RecipeService::active($recipe);
                $data['type'] = $recipe['type']; $data['tier'] = $recipe['tier'];
                foreach ($recipe['materials'] as $material) { $this->repository->activeCommodity($material['commodity_id']); }
            }
            $data['facility_recipe_id'] = $recipeId; $data['facility_recipe_version'] = $recipeVersion;
            $id = $this->repository->insertInstallation($systemId, $data);
            foreach ($recipe['materials'] ?? [] as $material) {
                $this->repository->saveRequirement($id, $material['commodity_id'], $material['required_quantity'], true);
                $requirement = $this->repository->requirement($id, $material['commodity_id']);
                $this->repository->revision(null, $requirement, $actor['id'], $reason, $operationId);
                $this->repository->audit('requirement', $id, 'create', null, $requirement, $actor['id'], $reason, $operationId);
            }
            $after = $this->repository->installation($id);
            $this->repository->audit('installation', $id, 'create', null, $after, $actor['id'], $reason, $operationId);
            return ['data' => $after, 'status' => 201];
        });
    }

    public function updateInstallation(array $actor, int $authVersion, string $key, string $id, array $input): array
    {
        $data = self::installationInput($input, true);
        $version = Input::version($input);
        $reason = Input::text($input, 'reason', 1000, false, true) ?? 'Actualización de planificación';
        return $this->mutations->run($actor, $authVersion, ['admin', 'viewer'], 'planning.installation.update', $key, $data + ['id' => $id, 'version' => $version, 'reason' => $reason], function(string $operationId) use ($id, $data, $version, $actor, $reason): array {
            [$parent, $before] = $this->repository->lockInstallation($id);
            self::active($parent);
            self::version($before, $version);
            $this->repository->updateInstallation($id, $data);
            $after = $this->repository->installation($id);
            $this->repository->audit('installation', $id, 'update', $before, $after, $actor['id'], $reason, $operationId);
            return ['data' => $after, 'status' => 200];
        });
    }

    public function archive(array $actor, int $authVersion, string $key, string $type, string $id, array $input, bool $restore = false): array
    {
        Input::fields($input, ['version', 'reason', 'confirmed']);
        if (!$restore) { Input::confirmation($input); }
        $version = Input::version($input);
        $reason = Input::text($input, 'reason', 1000, true, true);
        $action = $restore ? 'restore' : 'archive';
        $payload = ['id' => $id, 'version' => $version, 'reason' => $reason, 'confirmed' => !$restore];
        return $this->mutations->run($actor, $authVersion, ['admin', 'viewer'], "planning.$type.$action", $key, $payload, function(string $operationId) use ($type, $id, $restore, $version, $actor, $reason, $action): array {
            if ($type === 'system') {
                $before = $this->repository->system($id, true);
            } else {
                [$parent, $before] = $this->repository->lockInstallation($id);
                if ($restore) { self::active($parent); }
            }
            self::version($before, $version);
            if (($before['archived_at'] === null) === $restore) {
                throw new HttpError(409, 'archive_state', 'El estado de archivo cambió. Recargá los datos.');
            }
            $this->repository->archive($type, $id, $restore);
            $after = $type === 'system' ? $this->repository->system($id) : $this->repository->installation($id);
            $this->repository->audit($type, $id, $action, $before, $after, $actor['id'], $reason, $operationId);
            return ['data' => $after, 'status' => 200];
        });
    }

    public function saveRequirement(array $actor, int $authVersion, string $key, string $installationId, string $commodityId, array $input): array
    {
        Input::fields($input, ['required_quantity', 'version', 'reason']);
        $quantity = Input::quantity($input);
        $version = Input::version($input, true);
        $reason = Input::text($input, 'reason', 1000, true, true);
        $payload = ['installation_id' => $installationId, 'commodity_id' => $commodityId, 'required_quantity' => $quantity, 'version' => $version, 'reason' => $reason];
        return $this->mutations->run($actor, $authVersion, ['admin', 'viewer'], 'planning.requirement.save', $key, $payload, function(string $operationId) use ($installationId, $commodityId, $quantity, $version, $actor, $reason): array {
            [$parent, $installation] = $this->repository->lockInstallation($installationId);
            self::active($parent);
            self::active($installation);
            $this->repository->activeCommodity($commodityId);
            $before = $this->repository->requirement($installationId, $commodityId, true);
            if ($before === null && $version !== '0') { throw new HttpError(409, 'stale_version', 'El objetivo cambió o no existe. Recargá los datos.'); }
            if ($before !== null && $version === '0') { throw new HttpError(409, 'requirement_exists', 'Este material ya tiene un objetivo. Mostrá los retirados para editarlo o restaurarlo.'); }
            if ($before !== null) { self::version($before, $version); }
            $this->repository->saveRequirement($installationId, $commodityId, $quantity, $before === null);
            $after = $this->repository->requirement($installationId, $commodityId);
            $this->repository->revision($before, $after, $actor['id'], $reason, $operationId);
            $this->repository->audit('requirement', $installationId, $before === null ? 'create' : 'update', $before, $after, $actor['id'], $reason, $operationId);
            return ['data' => $after + ['system_id' => $parent['id']], 'status' => $before === null ? 201 : 200];
        });
    }

    public function removeRequirement(array $actor, int $authVersion, string $key, string $installationId, string $commodityId, array $input): array
    {
        Input::fields($input, ['version', 'reason', 'confirmed']);
        Input::confirmation($input);
        $version = Input::version($input);
        $reason = Input::text($input, 'reason', 1000, true, true);
        $payload = ['installation_id' => $installationId, 'commodity_id' => $commodityId, 'version' => $version, 'reason' => $reason, 'confirmed' => true];
        return $this->mutations->run($actor, $authVersion, ['admin', 'viewer'], 'planning.requirement.remove', $key, $payload, function(string $operationId) use ($installationId, $commodityId, $version, $actor, $reason): array {
            [$parent, $installation] = $this->repository->lockInstallation($installationId);
            self::active($parent);
            self::active($installation);
            $before = $this->repository->requirement($installationId, $commodityId, true) ?? throw new HttpError(404, 'not_found', 'No se encontró el objetivo.');
            self::version($before, $version);
            if (!(bool)$before['active']) { throw new HttpError(409, 'inactive', 'El objetivo ya está retirado.'); }
            $this->repository->removeRequirement($installationId, $commodityId);
            $after = $this->repository->requirement($installationId, $commodityId);
            $this->repository->revision($before, $after, $actor['id'], $reason, $operationId);
            $this->repository->audit('requirement', $installationId, 'archive', $before, $after, $actor['id'], $reason, $operationId);
            return ['data' => $after + ['system_id' => $parent['id']], 'status' => 200];
        });
    }

    private static function active(array $row): void
    {
        if ($row['archived_at'] !== null) { throw new HttpError(409, 'archived_parent', 'El sistema o instalación está archivado. Restauralo antes de modificar su planificación.'); }
    }

    private static function version(array $row, string $expected): void
    {
        if ($row['version'] !== $expected) { throw new HttpError(409, 'stale_version', 'Otro operador cambió estos datos. Recargá antes de volver a editar.'); }
        if ($expected === '4294967295') { throw new HttpError(409, 'version_exhausted', 'Se alcanzó el límite técnico de versiones.'); }
    }
}
