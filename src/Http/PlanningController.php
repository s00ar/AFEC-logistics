<?php
declare(strict_types=1);
namespace Afec\Http;

use Afec\App;
use Afec\Repositories\PlanningRepository;
use Afec\Repositories\RecipeRepository;
use Afec\Services\MutationService;
use Afec\Services\PlanningService;

final class PlanningController
{
    public static function write(App $app, string $route): never
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $allowed = match ($route) {
            'systems', 'installations', 'system/restore', 'installation/restore' => ['POST'],
            'system', 'installation' => ['PATCH', 'DELETE'],
            'requirement' => ['PUT', 'DELETE'],
            default => [],
        };
        if (!in_array($method, $allowed, true)) {
            header('Allow: ' . implode(', ', $allowed));
            throw new HttpError(405, 'method_not_allowed', 'Método no permitido.');
        }
        $actor = in_array($route, ['systems','installations'], true) ? $app->auth()->requireUser() : $app->auth()->requirePlanner();
        $app->session()->verifyCsrf();
        $key = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '';
        $input = Request::json();
        $version = $app->session()->authVersion();
        $repository = new PlanningRepository($app->database);
        $service = new PlanningService($repository, new MutationService($app->database), new RecipeRepository($app->database));
        $id = static fn(): string => Request::positiveId($_GET['id'] ?? null);
        $result = match ($route) {
            'systems' => $service->createSystem($actor, $version, $key, $input),
            'system' => $method === 'PATCH'
                ? $service->updateSystem($actor, $version, $key, $id(), $input)
                : $service->archive($actor, $version, $key, 'system', $id(), $input),
            'system/restore' => $service->archive($actor, $version, $key, 'system', $id(), $input, true),
            'installations' => $service->createInstallation($actor, $version, $key, Request::positiveId($_GET['system_id'] ?? null), $input),
            'installation' => $method === 'PATCH'
                ? $service->updateInstallation($actor, $version, $key, $id(), $input)
                : $service->archive($actor, $version, $key, 'installation', $id(), $input),
            'installation/restore' => $service->archive($actor, $version, $key, 'installation', $id(), $input, true),
            'requirement' => $method === 'PUT'
                ? $service->saveRequirement($actor, $version, $key, Request::positiveId($_GET['installation_id'] ?? null), Request::positiveId($_GET['commodity_id'] ?? null), $input)
                : $service->removeRequirement($actor, $version, $key, Request::positiveId($_GET['installation_id'] ?? null), Request::positiveId($_GET['commodity_id'] ?? null), $input),
        };
        Response::json(['data' => $result['data'], 'operation_id' => $result['operation_id'], 'replayed' => $result['replayed']], $result['status']);
    }
}
