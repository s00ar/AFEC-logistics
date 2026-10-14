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
    $app = require dirname(__DIR__) . '/src/bootstrap.php';
    Response::headers();
    Request::method('GET');
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (!in_array($path, [$app->url('/'), $app->url('/index.php')], true)) {
        throw new HttpError(404, 'not_found', 'No se encontró esta página.');
    }
    $user = $app->auth()->current();
    $canPlan = in_array($user['role'] ?? null, ['admin', 'operator'], true);
    $includeArchived = Request::flag($_GET['include_archived'] ?? null);
    if ($includeArchived) { $app->auth()->requirePlanner(); }
    $listPage = Request::page($_GET['list_page'] ?? null);
    $csrf = $app->session()->csrf();
    $page = $_GET['page'] ?? 'dashboard';
    if (!is_string($page) || !in_array($page, ['dashboard', 'system', 'users', 'login'], true)) {
        throw new HttpError(404, 'not_found', 'No se encontró esta página.');
    }
    if ($user === null && ($page === 'login' || !$app->config['app']['public_read'])) {
        $title = 'Iniciar sesión';
        $view = 'login';
    } elseif ($page === 'users') {
        $app->auth()->requireAdmin();
        $users = (new UserRepository($app->database))->list();
        $title = 'Usuarios';
        $view = 'users';
    } elseif ($page === 'system') {
        $system = (new ProgressRepository($app->database))->system(Request::positiveId($_GET['id'] ?? null), $includeArchived, $listPage);
        $pagination = $system['pagination'];
        $title = $system['name'];
        $view = 'system';
    } else {
        $listing = (new ProgressRepository($app->database))->systems($listPage, $includeArchived);
        $systems = $listing['items'];
        $pagination = ['page' => $listing['page'], 'has_more' => $listing['has_more']];
        $title = 'Sistemas de colonización';
        $view = 'dashboard';
    }
    require dirname(__DIR__) . '/templates/layout.php';
} catch (Throwable $error) {
    $status = $error instanceof HttpError ? $error->status : 503;
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    if ($app !== null && !$error instanceof HttpError) {
        $app->logError($error, $requestId);
    }
    $message = $error instanceof HttpError ? htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8') : 'El servicio no está disponible. El administrador debe revisar la configuración.';
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>AFEC Logística</title><body><main><h1>AFEC Logística</h1><p>' . $message . '</p><p>Referencia: ' . $requestId . '</p></main></body></html>';
}
