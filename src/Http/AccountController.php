<?php
declare(strict_types=1);
namespace Afec\Http;

use Afec\App;

final class AccountController
{
    public static function dispatch(App $app, string $route): never
    {
        if ($route === 'password/help') {
            Request::method('POST'); $app->session()->verifyCsrf();
            Response::json(['data'=>$app->accounts()->requestHelp(Request::json(), $_SERVER['REMOTE_ADDR'] ?? 'unknown')]);
        }
        $actor = str_starts_with($route, 'admin/') ? $app->auth()->requireAdmin() : $app->auth()->requireUser();
        if ($route === 'admin/password-help') {
            Request::method('GET');
            $listing = $app->accounts()->pending(Request::page($_GET['list_page'] ?? null));
            Response::json(['data'=>$listing['items'], 'total'=>$listing['total'], 'pagination'=>['page'=>$listing['page'],'has_more'=>$listing['has_more']]]);
        }
        Request::method('POST'); $app->session()->verifyCsrf();
        $key = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '';
        if ($route === 'account/password') {
            $remembered = $app->session()->remembered();
            $result = $app->accounts()->changePassword($actor, $app->session()->authVersion(), $key, Request::json(), $_SERVER['REMOTE_ADDR'] ?? 'unknown');
            // Only renew the version established by this operation, never a later admin revocation.
            $app->session()->login(['id'=>$actor['id'],'auth_version'=>$result['data']['auth_version']], $remembered);
            Response::json(['data'=>['changed'=>true, 'csrf_token'=>$app->session()->csrf()], 'operation_id'=>$result['operation_id'], 'replayed'=>$result['replayed']]);
        }
        $result = $app->accounts()->closeHelp($actor, $app->session()->authVersion(), $key, Request::positiveId($_GET['id'] ?? null), Request::json());
        Response::json(['data'=>$result['data'],'operation_id'=>$result['operation_id'],'replayed'=>$result['replayed']]);
    }
}
