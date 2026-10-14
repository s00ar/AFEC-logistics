<?php
declare(strict_types=1);
namespace Afec\Http;

use Afec\App;
use Afec\Repositories\CommodityRepository;
use Afec\Repositories\PlanningRepository;
use Afec\Services\CommodityService;

final class CommodityController
{
    public static function dispatch(App $app, string $route): never
    {
        $actor = $app->auth()->requireAdmin();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $repository = new CommodityRepository($app->database);
        $id = static fn(): string => Request::positiveId($_GET['id'] ?? null);
        if ($method === 'GET') {
            if ($route === 'admin/commodities') {
                $page = Request::page($_GET['list_page'] ?? null);
                $listing = $repository->listing($page, Request::flag($_GET['include_inactive'] ?? null));
                Response::json(['data'=>$listing['items'],'aliases_ready'=>$repository->aliasesReady(),'pagination'=>['page'=>$page,'has_more'=>$listing['has_more']]]);
            }
            if ($route === 'admin/commodity') { Response::json(['data'=>$repository->find($id())]); }
            if ($route === 'admin/commodity/history') {
                $repository->find($id()); $page = Request::page($_GET['list_page'] ?? null);
                $listing = (new PlanningRepository($app->database))->history('commodity',$id(),null,$page);
                Response::json(['data'=>$listing['items'],'pagination'=>['page'=>$page,'has_more'=>$listing['has_more']]]);
            }
        }
        $action = match ($route . ':' . $method) {
            'commodities:POST', 'admin/commodities:POST' => 'create',
            'admin/commodity:PATCH' => 'update', 'admin/commodity:DELETE' => 'deactivate',
            'admin/commodity/restore:POST' => 'restore', default => null,
        };
        if ($action === null) { throw new HttpError(405, 'method_not_allowed', 'Método no permitido.'); }
        $app->session()->verifyCsrf();
        $result = (new CommodityService($app->database))->write($actor,$app->session()->authVersion(),$_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '',$action,
            $action === 'create' ? null : $id(),Request::json());
        Response::json(['data'=>$result['data'],'operation_id'=>$result['operation_id'],'replayed'=>$result['replayed']],$result['status']);
    }
}
