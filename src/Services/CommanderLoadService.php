<?php
declare(strict_types=1);
namespace Afec\Services;

use Afec\Database;
use Afec\Http\HttpError;
use Afec\Repositories\CommanderLoadRepository;
use Afec\Repositories\PlanningRepository;
use Afec\Validation\PlanningInput as Input;
use Afec\Validation\LogisticsInput as L;

final class CommanderLoadService
{
    public function __construct(private readonly Database $database) {}

    public function save(array $actor, int $authVersion, string $key, string $id, array $input): array
    {
        Input::fields($input,['quantity','version']);
        if (!array_key_exists('quantity',$input)) { throw new HttpError(422,'validation','Indicá la palada o un valor vacío para quitarla.'); }
        if ($actor['role'] === 'viewer' && $actor['commander_id'] !== $id) { throw new HttpError(403,'forbidden','Solo podés configurar tu propia palada.'); }
        $data=['id'=>$id,'quantity'=>$input['quantity'] === '' || $input['quantity'] === null ? null : L::amount($input),'version'=>Input::version($input)];
        return (new MutationService($this->database))->run($actor,$authVersion,['admin','operator','viewer'],'logistics.load.save',$key,$data,
            function(string $op) use($actor,$id,$data): array {
                $repo = new CommanderLoadRepository($this->database);
                if (!$repo->ready()) { throw new HttpError(409,'schema_missing','Importá la migración 008 para guardar paladas.'); }
                $before = $repo->find($id,true);
                if (!$before['active']) { throw new HttpError(409,'inactive','El comandante no está activo.'); }
                if ($before['load_version'] !== $data['version'] || $before['load_version'] === '4294967295') { throw new HttpError(409,'stale_version','La palada cambió. Actualizá antes de guardar.'); }
                $query=$this->database->connection()->prepare('UPDATE commanders SET standard_load_quantity=?,load_version=load_version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?');
                $query->execute([$data['quantity'],$id]);
                $after=$repo->find($id);
                (new PlanningRepository($this->database))->audit('commander',$id,'standard_load',$before,$after,$actor['id'],'Configuración de palada estándar',$op);
                return ['data'=>$after,'status'=>200];
            });
    }
}
