<?php
declare(strict_types=1);
namespace Afec\Services;

use Afec\Database;
use Afec\Http\HttpError;
use Afec\Repositories\PlanningRepository;
use Afec\Repositories\UserRepository;
use Afec\Validation\PlanningInput as Input;
use PDOException;

final class UserService
{
    public function __construct(private readonly Database $database) {}

    public function write(array $actor, int $authVersion, string $key, string $action, ?string $id, array $input): array
    {
        $fields = match ($action) {
            'create' => ['username','display_name','role','password','reason'],
            'update' => ['username','display_name','role','active','version','reason'],
            'password' => ['password','version','reason'],
            default => ['version','reason','confirmed'],
        };
        Input::fields($input, $fields);
        $data = ['reason' => Input::text($input, 'reason', 1000, true, true)];
        if ($action !== 'create') { $data['version'] = Input::version($input); $data['id'] = $id; }
        if (in_array($action, ['create','update'], true)) {
            $data['username'] = Input::text($input, 'username', 120, true);
            if (!preg_match('/^[a-zA-Z0-9_.@+-]{3,120}$/D', $data['username'])) { throw new HttpError(422, 'validation', 'El usuario admite letras, números y _.@+-; mínimo 3 caracteres.'); }
            $data['display_name'] = Input::text($input, 'display_name', 160, true);
            $data['role'] = $input['role'] ?? null;
            if (!in_array($data['role'], ['admin','operator','viewer'], true)) { throw new HttpError(422, 'validation', 'Seleccioná un rol válido.'); }
            if ($action === 'update') {
                if (!is_bool($input['active'] ?? null)) { throw new HttpError(422, 'validation', 'El estado de la cuenta debe ser verdadero o falso.'); }
                $data['active'] = $input['active'];
            }
        }
        if (in_array($action, ['create','password'], true)) {
            $password = $input['password'] ?? null;
            if (!is_string($password) || strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) { throw new HttpError(422, 'validation', 'La contraseña debe tener entre 12 y 72 bytes.'); }
            $data['password'] = $password; // Kept only in memory; no fast digest, receipt, audit or browser recovery storage contains it.
        }
        if ($action === 'delete') { Input::confirmation($input); }
        $payload = $data; unset($payload['password']);
        try {
            return (new MutationService($this->database))->run($actor, $authVersion, ['admin'], 'admin.user.' . $action, $key, $payload, function(string $op) use ($actor, $action, $id, $data): array {
                $db = $this->database->connection();
                $before = null;
                if ($action !== 'create') {
                    $q = $db->prepare('SELECT id,username,display_name,role,active,commander_id,version,deleted_at,auth_version FROM users WHERE id = ? FOR UPDATE');
                    $q->execute([$id]);
                    $row = $q->fetch() ?: throw new HttpError(404, 'not_found', 'No se encontró la cuenta.');
                    $before = UserRepository::adminView($row);
                    if ($before['version'] !== $data['version'] || $data['version'] === '4294967295') { throw new HttpError(409, 'stale_version', 'La cuenta cambió. Actualizá los datos.'); }
                    if ($action !== 'restore' && $row['deleted_at'] !== null) { throw new HttpError(409, 'deleted_user', 'Restaurá la cuenta antes de editarla.'); }
                    $disables = $action === 'delete' || ($action === 'update' && (!$data['active'] || $data['role'] !== 'admin'));
                    if ($disables && $row['role'] === 'admin' && (bool)$row['active']) {
                        $admins = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND active=1 AND deleted_at IS NULL")->fetchColumn();
                        if ($admins <= 1) { throw new HttpError(409, 'last_admin', 'Debe conservarse al menos un administrador activo.'); }
                    }
                    if ($id === $actor['id'] && $disables) { throw new HttpError(409, 'self_disable', 'Otro administrador debe cambiar tu rol o eliminar/desactivar tu cuenta.'); }
                    if ((int)$row['auth_version'] === 4294967295) { throw new HttpError(409, 'version_exhausted', 'Se alcanzó el límite de versiones de acceso.'); }
                }
                if ($action === 'create') {
                    $q = $db->prepare('INSERT INTO commanders (display_name,normalized_name) VALUES (?,?)');
                    $q->execute([$data['display_name'], mb_strtolower($data['display_name'])]);
                    $commander = $db->lastInsertId();
                    $q = $db->prepare('INSERT INTO users (username,display_name,role,password_hash,commander_id) VALUES (?,?,?,?,?)');
                    $q->execute([$data['username'],$data['display_name'],$data['role'],password_hash($data['password'], PASSWORD_DEFAULT),$commander]);
                    $id = $db->lastInsertId();
                } elseif ($action === 'update') {
                    $q = $db->prepare('UPDATE users SET username=?,display_name=?,role=?,active=?,version=version+1,auth_version=auth_version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?');
                    $q->execute([$data['username'],$data['display_name'],$data['role'],(int)$data['active'],$id]);
                } elseif ($action === 'password') {
                    $q = $db->prepare('UPDATE users SET password_hash=?,version=version+1,auth_version=auth_version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?');
                    $q->execute([password_hash($data['password'], PASSWORD_DEFAULT),$id]);
                } elseif ($action === 'delete') {
                    $q = $db->prepare('UPDATE users SET active=0,deleted_at=UTC_TIMESTAMP(6),version=version+1,auth_version=auth_version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?');
                    $q->execute([$id]);
                } else {
                    if ($row['deleted_at'] === null) { throw new HttpError(409, 'archive_state', 'La cuenta no está eliminada.'); }
                    $q = $db->prepare('UPDATE users SET active=1,deleted_at=NULL,version=version+1,auth_version=auth_version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?');
                    $q->execute([$id]);
                }
                $after = UserRepository::adminView((new UserRepository($this->database))->findById($id));
                $auditAfter = $after + (in_array($action,['create','password'],true) ? ['password_changed'=>true] : []);
                (new PlanningRepository($this->database))->audit('user',$id,$action,$before,$auditAfter,$actor['id'],$data['reason'],$op);
                return ['data'=>$after,'status'=>$action==='create'?201:200];
            },$data['password']??null);
        } catch (PDOException $error) {
            if ((int)($error->errorInfo[1] ?? 0) === 1062) { throw new HttpError(409,'username_exists','Ese usuario ya existe, incluso si su cuenta está eliminada.'); }
            throw $error;
        }
    }
}
