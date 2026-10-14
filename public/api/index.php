<?php
declare(strict_types=1);
ini_set('display_errors', '0');

use Afec\Http\HttpError;
use Afec\Http\Request;
use Afec\Http\Response;
use Afec\Http\PlanningController;
use Afec\Http\RecipeController;
use Afec\Http\ExpansionController;
use Afec\Repositories\PlanningRepository;
use Afec\Repositories\ProgressRepository;
use Afec\Repositories\UserRepository;

$app = null;
$requestId = bin2hex(random_bytes(8));
try {
    $app = require dirname(__DIR__, 2) . '/src/bootstrap.php';
    if (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) !== $app->url('/api/index.php')) {
        throw new HttpError(404, 'not_found', 'No se encontró esta operación.');
    }
    $route = $_GET['route'] ?? 'session';
    if (!is_string($route)) {
        throw new HttpError(400, 'invalid_route', 'Ruta no válida.');
    }
    $archiveFilter = static function() use ($app): bool {
        $flag = Request::flag($_GET['include_archived'] ?? null);
        if ($flag) { $app->auth()->requirePlanner(); }
        return $flag;
    };
    switch ($route) {
        case 'facility/recipes': case 'facility/recipe': case 'facility/recipe/restore': case 'facility/recipe/history':
            RecipeController::dispatch($app, $route);
        case 'admin/user': case 'admin/roles': case 'admin/user/password': case 'admin/user/restore':
        case 'installation/status': case 'wishlist': case 'wishlist/destinations': case 'wishlist/entries': case 'wishlist/entry':
        case 'sources': case 'source': case 'source/restore': case 'deliveries': case 'movement/withdraw':
        case 'movements': case 'logistics/options': case 'operation':
            ExpansionController::dispatch($app,$route);
        case 'session':
            Request::method('GET');
            $user = $app->auth()->current();
            Response::json(['data' => ['user' => $user, 'csrf_token' => $app->session()->csrf()]]);
        case 'login':
            Request::method('POST');
            $app->session()->verifyCsrf();
            $user = $app->auth()->login(Request::json(), $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            Response::json(['data' => ['user' => $user, 'csrf_token' => $app->session()->csrf()]]);
        case 'logout':
            Request::method('POST');
            $app->session()->verifyCsrf();
            $app->session()->logout();
            Response::json(['data' => ['user' => null, 'csrf_token' => $app->session()->csrf()]]);
        case 'systems':
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { PlanningController::write($app, $route); }
            Request::method('GET');
            if (!$app->config['app']['public_read']) {
                $app->auth()->requireUser();
            }
            $listing = (new ProgressRepository($app->database))->systems(Request::page($_GET['list_page'] ?? null), $archiveFilter());
            Response::json(['data' => $listing['items'], 'pagination' => ['page' => $listing['page'], 'has_more' => $listing['has_more']]]);
        case 'system':
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { PlanningController::write($app, $route); }
            Request::method('GET');
            if (!$app->config['app']['public_read']) {
                $app->auth()->requireUser();
            }
            Response::json(['data' => (new ProgressRepository($app->database))->system(Request::positiveId($_GET['id'] ?? null), $archiveFilter(), Request::page($_GET['list_page'] ?? null))]);
        case 'installation':
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { PlanningController::write($app, $route); }
            if (!$app->config['app']['public_read']) { $app->auth()->requireUser(); }
            Response::json(['data' => (new PlanningRepository($app->database))->installation(Request::positiveId($_GET['id'] ?? null))]);
        case 'installations':
        case 'system/restore':
        case 'installation/restore':
        case 'requirement':
            PlanningController::write($app, $route);
        case 'planning/history':
            Request::method('GET');
            $app->auth()->requirePlanner();
            $type = $_GET['type'] ?? null;
            if (!is_string($type) || !in_array($type, ['system', 'installation', 'requirement'], true)) {
                throw new HttpError(422, 'invalid_type', 'Tipo de historial no válido.');
            }
            $id = Request::positiveId($_GET['id'] ?? null);
            $repository = new PlanningRepository($app->database);
            if ($type === 'system') { $repository->system($id); } else { $repository->installation($id); }
            $commodityId = $type === 'requirement' ? Request::positiveId($_GET['commodity_id'] ?? null) : null;
            if ($type === 'requirement' && !$repository->requirement($id, $commodityId)) {
                throw new HttpError(404, 'not_found', 'No se encontró el objetivo.');
            }
            $listing = $repository->history($type, $id, $commodityId, Request::page($_GET['list_page'] ?? null));
            Response::json(['data' => $listing['items'], 'pagination' => ['page' => $listing['page'], 'has_more' => $listing['has_more']]]);
        case 'admin/users':
            ExpansionController::dispatch($app,$route);
        case 'commodities':
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { PlanningController::write($app, $route); }
            Request::method('GET');
            if (!$app->config['app']['public_read']) { $app->auth()->requireUser(); }
            $rows = $app->database->connection()->query('SELECT CAST(id AS CHAR) AS id, code, name_es, name_en FROM commodities WHERE active = 1 ORDER BY name_es')->fetchAll();
            Response::json(['data' => $rows]);
        default:
            throw new HttpError(404, 'not_found', 'No se encontró la operación solicitada.');
    }
} catch (HttpError $error) {
    Response::json(['error' => ['code' => $error->errorCode, 'message' => $error->getMessage(), 'fields' => $error->fields], 'request_id' => $requestId], $error->status);
} catch (Throwable $error) {
    if ($app !== null) {
        $app->logError($error, $requestId);
    }
    // bootstrap may fail before autoload/Response is available; never echo its error.
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['error' => ['code' => 'service_unavailable', 'message' => 'El servicio no está disponible. El administrador debe revisar la configuración.'], 'request_id' => $requestId]);
}
