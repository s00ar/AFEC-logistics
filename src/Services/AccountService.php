<?php
declare(strict_types=1);
namespace Afec\Services;

use Afec\Database;
use Afec\Http\HttpError;
use Afec\Repositories\AccountRepository;
use Afec\Repositories\PasswordHelpRepository;
use Afec\Repositories\PlanningRepository;
use Afec\Repositories\UserRepository;
use Afec\Security\AccountThrottle;
use Afec\Validation\PlanningInput as Input;

final class AccountService
{
    public function __construct(private readonly Database $database, private readonly PasswordHelpRepository $requests) {}

    public function requestHelp(array $input, string $address): array
    {
        Input::fields($input, ['username']);
        $username = $input['username'] ?? null;
        if (!is_string($username) || !preg_match('/^[a-zA-Z0-9_.@+-]{3,120}$/D', $username)) { throw new HttpError(422, 'validation', 'Ingresá tu usuario de acceso.'); }
        try {
            (new AccountThrottle($this->database))->run('password-help', $username, $address, 3, 10, function() use ($username): void {
                $db = $this->database->connection(); $db->beginTransaction();
                try {
                    // Do not attribute this anonymous request to the account owner in the audit log.
                    $q = $db->prepare('SELECT id FROM users WHERE username=? AND active=1 AND deleted_at IS NULL FOR UPDATE');
                    $q->execute([$username]); $id = $q->fetchColumn();
                    if ($id !== false) {
                        $resolved = (new AccountRepository($this->database))->resolution((string)$id);
                        $now = $db->query('SELECT UTC_TIMESTAMP(6)')->fetchColumn();
                        $this->requests->request((string)$id, $now, $resolved);
                    }
                    $db->commit();
                } catch (\Throwable $error) { if ($db->inTransaction()) { $db->rollBack(); } throw $error; }
            });
        } catch (HttpError $error) { if ($error->status !== 429) { throw $error; } }
        // Identical response for unknown, inactive, duplicate and rate-limited requests.
        return ['message' => 'Si la cuenta está activa, un administrador verá tu solicitud al ingresar. Tu contraseña actual sigue funcionando.'];
    }

    public function pending(int $page = 1): array
    {
        $all = (new AccountRepository($this->database))->pendingUsers($this->requests->all());
        return ['items' => array_slice($all, ($page - 1) * 25, 25), 'total' => count($all), 'page' => $page, 'has_more' => count($all) > $page * 25];
    }

    public function closeHelp(array $actor, int $authVersion, string $key, string $id, array $input): array
    {
        Input::fields($input, ['request_id','reason']);
        $requestId = $input['request_id'] ?? null;
        if (!is_string($requestId) || !preg_match('/^[a-f0-9]{32}$/D', $requestId)) { throw new HttpError(422, 'validation', 'Recargá la solicitud antes de atenderla.'); }
        $reason = Input::text($input, 'reason', 1000, true, true);
        return (new MutationService($this->database))->run($actor, $authVersion, ['admin'], 'admin.user.help.close', $key, ['id'=>$id,'request_id'=>$requestId,'reason'=>$reason], function(string $op) use ($actor,$id,$requestId,$reason): array {
            $repo = new AccountRepository($this->database);
            if ($repo->lockUser($id) === null) { throw new HttpError(404, 'not_found', 'No se encontró la cuenta.'); }
            $request = $this->requests->find($id);
            if ($request === null || $request['request_id'] !== $requestId) { throw new HttpError(409, 'stale_request', 'La solicitud cambió. Recargá antes de continuar.'); }
            (new PlanningRepository($this->database))->audit('user', $id, 'password_help_closed', null, ['request_id'=>$requestId], $actor['id'], $reason, $op);
            return ['data'=>['closed'=>true], 'status'=>200];
        });
    }

    public function changePassword(array $actor, int $authVersion, string $key, array $input, string $address): array
    {
        Input::fields($input, ['current_password','new_password','confirmation']);
        $current = $input['current_password'] ?? null; $new = $input['new_password'] ?? null;
        if (!is_string($current) || strlen($current) < 1 || strlen($current) > 1024 || str_contains($current, "\0") || !is_string($new) || strlen($new) < 12 || strlen($new) > 72 || str_contains($new, "\0")) { throw new HttpError(422, 'validation', 'Ingresá la contraseña actual y una nueva de entre 12 y 72 bytes.'); }
        if (($input['confirmation'] ?? null) !== $new) { throw new HttpError(422, 'validation', 'Las contraseñas nuevas no coinciden.'); }
        if ($current === $new) { throw new HttpError(422, 'validation', 'Elegí una contraseña diferente de la actual.'); }
        return (new AccountThrottle($this->database))->run('own-password', $actor['id'], $address, 8, 30, function() use ($actor,$authVersion,$key,$current,$new): array {
            return (new MutationService($this->database))->run($actor, $authVersion, ['admin', 'viewer'], 'account.password', $key, ['id'=>$actor['id']], function(string $op) use ($actor,$current,$new): array {
                $repo = new AccountRepository($this->database); $row = $repo->lockUser($actor['id']);
                if (!password_verify($current, $row['password_hash'])) { throw new HttpError(403, 'current_password', 'La contraseña actual no es correcta.'); }
                if ((int)$row['version'] === 4294967295 || (int)$row['auth_version'] === 4294967295) { throw new HttpError(409, 'version_exhausted', 'La cuenta requiere revisión de un administrador.'); }
                $repo->changePassword($actor['id'], $new);
                (new PlanningRepository($this->database))->audit('user', $actor['id'], 'password', null, ['password_changed'=>true,'self_service'=>true], $actor['id'], 'Cambio de contraseña desde Mi cuenta.', $op);
                return ['data'=>['changed'=>true,'auth_version'=>(int)$row['auth_version']+1], 'status'=>200];
            }, $new);
        });
    }
}
