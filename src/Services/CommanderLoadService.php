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

    public function identity(array $actor,int $authVersion,string $key,array $input): array
    {
        Input::fields($input,[]);
        return (new MutationService($this->database))->run($actor,$authVersion,['admin', 'viewer'],'logistics.commander.identity',$key,[],function(string $op) use($actor): array {
            $id=(new \Afec\Repositories\LogisticsRepository($this->database))->ownCommander($actor);
            return ['data'=>(new CommanderLoadRepository($this->database))->find($id),'status'=>200];
        });
    }

    public function save(array $actor, int $authVersion, string $key, string $id, array $input): array
    {
        Input::fields($input,['quantity','version']);
        if (!array_key_exists('quantity',$input)) { throw new HttpError(422,'validation','Indicá la palada o un valor vacío para quitarla.'); }
        $data=['id'=>$id,'quantity'=>$input['quantity'] === '' || $input['quantity'] === null ? null : L::amount($input),'version'=>Input::version($input)];
        return (new MutationService($this->database))->run($actor,$authVersion,['admin', 'viewer'],'logistics.load.save',$key,$data,
            function(string $op) use($actor,$id,$data): array {
                $repo = new CommanderLoadRepository($this->database);
                $before = $repo->find($id,true);
                if (!$before['active']) { throw new HttpError(409,'inactive','El comandante no está activo.'); }
                if ($before['load_version'] !== $data['version'] || $before['load_version'] === '4294967295') { throw new HttpError(409,'stale_version','La palada cambió. Actualizá antes de guardar.'); }
                $after=$before;
                $after['standard_load_quantity']=$data['quantity'];
                $after['load_version']=(string)((int)$before['load_version']+1);
                if ($repo->ready()) {
                    $query=$this->database->connection()->prepare('UPDATE commanders SET standard_load_quantity=?,load_version=?,updated_at=UTC_TIMESTAMP(6) WHERE id=?');
                    $query->execute([$data['quantity'],$after['load_version'],$id]);
                }
                (new PlanningRepository($this->database))->audit('commander',$id,'standard_load',$before,$after,$actor['id'],'Configuración de palada estándar',$op);
                return ['data'=>$after,'status'=>200];
            });
    }
}
