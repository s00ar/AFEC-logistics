<?php
declare(strict_types=1);
ini_set('display_errors', '0');

use Afec\Http\HttpError;
use Afec\Http\Request;
use Afec\Http\Response;
use Afec\Repositories\ProgressRepository;
use Afec\Repositories\UserRepository;
use Afec\Repositories\LogisticsRepository;
use Afec\Repositories\RecipeRepository;
use Afec\Services\RecipeCatalogue;
use Afec\Services\RecipeCatalogueService;
use Afec\Validation\LogisticsInput;

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
    $canPlan = $user !== null;
    $includeArchived = Request::flag($_GET['include_archived'] ?? null);
    if ($includeArchived) { $app->auth()->requirePlanner(); }
    $listPage = Request::page($_GET['list_page'] ?? null);
    $csrf = $app->session()->csrf();
    $page = $_GET['page'] ?? 'dashboard';
    if (!is_string($page) || !in_array($page, ['dashboard', 'system', 'users', 'login','wishlist', 'recipes','commodities','account','password-help','password-requests','markets'], true)) {
        throw new HttpError(404, 'not_found', 'No se encontró esta página.');
    }
    if ($user === null && ($page === 'login' || (!$app->config['app']['public_read'] && $page !== 'password-help'))) {
        $title = 'Iniciar sesión';
        $view = 'login';
    } elseif ($page === 'markets') {
        $app->auth()->requireUser();
        $marketRepository=new \Afec\Repositories\MarketRepository($app->database,$app->config['markets']['stale_days']);
        $marketsReady=$marketRepository->ready(); $marketFilters=\Afec\Repositories\MarketRepository::filters($_GET);
        $marketOptions=$marketsReady?$marketRepository->options():['stations'=>[],'systems'=>[],'economies'=>[],'stale_days'=>$app->config['markets']['stale_days']];
        $marketListing=$marketsReady?($marketFilters['missing']?$marketRepository->missing($marketFilters['system'],$listPage):$marketRepository->listing($marketFilters,$listPage)):['items'=>[],'pagination'=>['page'=>1,'has_more'=>false]];
        $pagination=$marketListing['pagination'];
        $marketImports=$marketsReady && $user['role']==='admin'?$marketRepository->imports()['items']:[];
        $marketCommodities=$marketsReady && $user['role']==='admin'?(new \Afec\Repositories\CommodityRepository($app->database))->rows():[];
        $title='Mercados del Sistema'; $view='markets';
    } elseif ($page === 'account') {
        $app->auth()->requireUser(); $title = 'Mi cuenta'; $view = 'account';
    } elseif ($page === 'password-help') {
        $title = 'Ayuda para ingresar'; $view = 'password-help';
    } elseif ($page === 'password-requests') {
        $app->auth()->requireAdmin();
        $passwordRequests = $app->accounts()->pending($listPage);
        $pagination = ['page'=>$listPage, 'has_more'=>$passwordRequests['has_more']];
        $title = 'Solicitudes de ayuda'; $view = 'password-requests';
    } elseif ($page === 'recipes') {
        $app->auth()->requirePlanner();
        $listing = (new RecipeRepository($app->database))->listing($listPage, $includeArchived);
        $recipes = $listing['items'];
        $catalogue = (new RecipeCatalogueService($app->database, new RecipeCatalogue(dirname(__DIR__) . '/resources/catalogue')))->preview();
        $pagination = ['page' => $listPage, 'has_more' => $listing['has_more']];
        $title = 'Facilities y recetas'; $view = 'recipes';
    } elseif ($page === 'commodities') {
        $app->auth()->requireUser();
        $repository = new \Afec\Repositories\CommodityRepository($app->database);
        $inactive = Request::flag($_GET['include_inactive'] ?? null);
        $listing = $repository->listing($listPage, $inactive);
        $commodities = $listing['items']; $aliasesReady = $repository->aliasesReady();
        $pagination = ['page'=>$listPage,'has_more'=>$listing['has_more']];
        $title = 'Mercancías'; $view = 'commodities';
    } elseif ($page === 'users') {
        $app->auth()->requireAdmin();
        $listing = (new UserRepository($app->database))->listing($listPage);
        $users = $listing['items'];
        $pagination = ['page'=>$listPage,'has_more'=>$listing['has_more']];
        $title = 'Usuarios';
        $view = 'users';
    } elseif ($page === 'wishlist') {
        $app->auth()->requireUser();
        if (array_diff(array_keys($_GET),['page','scope','list_page','selection_page'])) { throw new HttpError(422,'validation','La lista personal corresponde siempre al usuario autenticado.'); }
        $scope = LogisticsInput::choice(['scope'=>$_GET['scope']??'personal'],'scope',['personal','fleet']);
        $wishlist=(new LogisticsRepository($app->database))->wishlist($scope,$user['id'],$listPage,Request::page($_GET['selection_page']??null));
        $title=$scope==='fleet'?'Lista de la flota':'Mi lista de deseos';
        $view='wishlist';
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
    $passwordHelpTotal = 0; $passwordHelpError = false;
    if (($user['role'] ?? null) === 'admin') {
        try { $passwordHelpTotal = ($passwordRequests ?? $app->accounts()->pending())['total']; }
        catch (Throwable $error) { $passwordHelpError = true; $app->logError($error, $requestId); }
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
