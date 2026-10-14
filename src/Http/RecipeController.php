<?php
declare(strict_types=1);
namespace Afec\Http;

use Afec\App;
use Afec\Repositories\PlanningRepository;
use Afec\Repositories\RecipeRepository;
use Afec\Services\MutationService;
use Afec\Services\RecipeService;

final class RecipeController
{
    public static function dispatch(App $app, string $route): never
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $allowed = match ($route) { 'facility/recipes' => ['GET', 'POST'], 'facility/recipe' => ['GET', 'PATCH', 'DELETE'], 'facility/recipe/restore' => ['POST'], 'facility/recipe/history' => ['GET'] };
        if (!in_array($method, $allowed, true)) { header('Allow: ' . implode(', ', $allowed)); throw new HttpError(405, 'method_not_allowed', 'Método no permitido.'); }
        $actor = $app->auth()->requirePlanner();
        $recipes = new RecipeRepository($app->database);
        $planning = new PlanningRepository($app->database);
        $id = static fn(): string => Request::positiveId($_GET['id'] ?? null);
        if ($method === 'GET') {
            if ($route === 'facility/recipes') {
                $listing = $recipes->listing(Request::page($_GET['list_page'] ?? null), Request::flag($_GET['include_archived'] ?? null));
                Response::json(['data' => $listing['items'], 'pagination' => ['page' => $listing['page'], 'has_more' => $listing['has_more']]]);
            }
            if ($route === 'facility/recipe/history') {
                $recipes->snapshot($id());
                $listing = $planning->history('facility_recipe', $id(), null, Request::page($_GET['list_page'] ?? null));
                Response::json(['data' => $listing['items'], 'pagination' => ['page' => $listing['page'], 'has_more' => $listing['has_more']]]);
            }
            Response::json(['data' => $recipes->snapshot($id())]);
        }
        $app->session()->verifyCsrf();
        $key = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '';
        $version = $app->session()->authVersion();
        $input = Request::json();
        $service = new RecipeService($recipes, $planning, new MutationService($app->database));
        $result = match ($route) {
            'facility/recipes' => $service->save($actor, $version, $key, null, $input),
            'facility/recipe' => $method === 'PATCH' ? $service->save($actor, $version, $key, $id(), $input) : $service->archive($actor, $version, $key, $id(), $input, false),
            'facility/recipe/restore' => $service->archive($actor, $version, $key, $id(), $input, true),
        };
        Response::json(['data' => $result['data'], 'operation_id' => $result['operation_id'], 'replayed' => $result['replayed']], $result['status']);
    }
}
