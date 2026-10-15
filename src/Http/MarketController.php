<?php
declare(strict_types=1);
namespace Afec\Http;

use Afec\App;
use Afec\Repositories\MarketRepository;
use Afec\Services\MarketService;
use Afec\Validation\PlanningInput as Input;

final class MarketController
{
    private static function upload(string $field): array
    {
        $file=$_FILES[$field]??null;
        if($file===null || ($file['error']??null)===UPLOAD_ERR_NO_FILE) { return [null,null]; }
        if(!is_array($file) || !is_int($file['error']??null) || $file['error']!==UPLOAD_ERR_OK || !is_string($file['tmp_name']??null) || !is_uploaded_file($file['tmp_name']) || ($file['size']??0)>1048576) { throw new HttpError(422,'market_upload','No se pudo leer el CSV. Máximo 1 MiB por archivo.'); }
        $name=basename(str_replace('\\','/',Input::text($file,'name',255,true)));
        if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='csv') { throw new HttpError(422,'market_upload','Seleccioná archivos .csv.'); }
        return [file_get_contents($file['tmp_name']),$name];
    }
    public static function dispatch(App $app,string $route): never
    {
        $method=$_SERVER['REQUEST_METHOD']??'GET';
        $allowed=match($route) { 'markets','markets/options','markets/imports','markets/history'=>['GET'], 'markets/import'=>['POST'], 'markets/stock','markets/station','markets/product'=>['PATCH'] };
        if(!in_array($method,$allowed,true)) { header('Allow: '.implode(', ',$allowed)); throw new HttpError(405,'method_not_allowed','Método no permitido.'); }
        $actor=$app->auth()->requireUser();
        $repository=new MarketRepository($app->database,$app->config['markets']['stale_days']);
        if(!$repository->ready()) { throw new HttpError(503,'market_schema','Mercados requiere aplicar la migración 009. El resto de la aplicación sigue disponible.'); }
        if($method==='GET') {
            $page=Request::page($_GET['list_page']??null);
            if($route==='markets/options') { Response::json(['data'=>$repository->options()]); }
            if($route==='markets/imports') { $listing=$repository->imports($page); }
            elseif($route==='markets/history') {
                $rows=$repository->rows('SELECT CAST(id AS CHAR) AS id,CAST(import_id AS CHAR) AS import_id,CAST(quantity AS CHAR) AS quantity,available,verified,observed_on,source,verification,applied,created_at FROM market_observations WHERE station_id=? AND product_id=? ORDER BY id DESC LIMIT 26 OFFSET '.(($page-1)*25),[Request::positiveId($_GET['station_id']??null),Request::positiveId($_GET['product_id']??null)]);
                $more=count($rows)>25; if($more) { array_pop($rows); } $listing=['items'=>$rows,'pagination'=>['page'=>$page,'has_more'=>$more]];
            } else { $filters=MarketRepository::filters($_GET); $listing=$filters['missing']?$repository->missing($filters['system'],$page):$repository->listing($filters,$page); }
            Response::json(['data'=>$listing['items'],'pagination'=>$listing['pagination']]);
        }
        $app->session()->verifyCsrf(); $service=new MarketService($app->database);
        if($route==='markets/import') {
            if(!str_starts_with(strtolower($_SERVER['CONTENT_TYPE']??''),'multipart/form-data') || (int)($_SERVER['CONTENT_LENGTH']??0)>2200000) { throw new HttpError(413,'market_upload','Enviá los CSV como archivos, hasta 1 MiB por archivo.'); }
            Input::fields($_POST,['action','system','review_hash','confirmed']);
            if(array_diff(array_keys($_FILES),['stock_file','summary_file'])) { throw new HttpError(422,'market_upload','Archivo no permitido.'); }
            [$stock,$stockName]=self::upload('stock_file'); [$summary,$summaryName]=self::upload('summary_file');
            $context=Input::text($_POST,'system',255);
            if(($_POST['action']??null)==='preview') { Response::json(['data'=>$service->preview($stock,$summary,$context)]); }
            if(($_POST['action']??null)!=='apply' || ($_POST['confirmed']??null)!=='1') { throw new HttpError(422,'market_review','Validá los CSV y confirmá la importación.'); }
            $hash=Input::text($_POST,'review_hash',64,true);
            $result=$service->import($actor,$app->session()->authVersion(),$_SERVER['HTTP_IDEMPOTENCY_KEY']??'',$stock,$summary,$context,$stockName,$summaryName,$hash);
        } else {
            $kind=substr($route,8);
            $result=$service->edit($actor,$app->session()->authVersion(),$_SERVER['HTTP_IDEMPOTENCY_KEY']??'',$kind,Request::positiveId($_GET['id']??null),Request::json());
        }
        Response::json(['data'=>$result['data'],'operation_id'=>$result['operation_id'],'replayed'=>$result['replayed']],$result['status']);
    }
}
