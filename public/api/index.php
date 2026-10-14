<?php
declare(strict_types=1);
ini_set('display_errors', '0');

use Afec\Http\HttpError;
use Afec\Http\Request;
use Afec\Http\Response;
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
    switch ($route) {
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
            Request::method('GET');
            if (!$app->config['app']['public_read']) {
                $app->auth()->requireUser();
            }
            Response::json(['data' => (new ProgressRepository($app->database))->systems()]);
        case 'system':
            Request::method('GET');
            if (!$app->config['app']['public_read']) {
                $app->auth()->requireUser();
            }
            Response::json(['data' => (new ProgressRepository($app->database))->system(Request::positiveId($_GET['id'] ?? null))]);
        case 'admin/users':
            Request::method('GET');
            $app->auth()->requireAdmin();
            Response::json(['data' => (new UserRepository($app->database))->list()]);
        case 'commodities':
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
