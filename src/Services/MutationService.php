<?php
declare(strict_types=1);
namespace Afec\Services;

use Afec\Database;
use Afec\Http\HttpError;
use Afec\Security\UserRole;
use PDOException;

/** All domain writes share permission revalidation, idempotency and a single transaction. */
final class MutationService
{
    public function __construct(private readonly Database $database) {}

    public function run(array $actor, int $authVersion, array $roles, string $scope, string $key, array $payload, callable $write, #[\SensitiveParameter] ?string $secret = null): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{16,100}$/D', $key)) {
            throw new HttpError(422, 'idempotency_required', 'Falta una clave válida para identificar esta operación.');
        }
        ksort($payload);
        $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), true);
        $db = $this->database->connection();
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $db->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
                $db->beginTransaction();
                if (str_starts_with($scope, 'admin.user.')) {
                    $db->query("SELECT name FROM permission_locks WHERE name = 'users' FOR UPDATE")->fetch();
                }
                // This lock serializes duplicate requests by one actor and prevents a concurrent role revocation.
                $query = $db->prepare('SELECT active, role, auth_version FROM users WHERE id = ? FOR UPDATE');
                $query->execute([$actor['id']]);
                $user = $query->fetch();
                if (!$user || !(bool)$user['active'] || (int)$user['auth_version'] !== $authVersion) {
                    throw new HttpError(401, 'unauthenticated', 'La sesión ya no es válida.');
                }
                if (!in_array(UserRole::canonical($user['role']), $roles, true)) {
                    throw new HttpError(403, 'forbidden', 'No tenés permiso para esta operación.');
                }
                $query = $db->prepare('SELECT id, request_hash, secret_hash, result_json FROM operations WHERE actor_user_id = ? AND scope = ? AND idempotency_key = ? FOR UPDATE');
                $query->execute([$actor['id'], $scope, $key]);
                $existing = $query->fetch();
                if ($existing) {
                    if (!hash_equals($existing['request_hash'], $hash) || ($existing['secret_hash'] !== null) !== ($secret !== null) || ($secret !== null && !password_verify($secret,$existing['secret_hash']))) {
                        throw new HttpError(409, 'idempotency_conflict', 'La clave ya corresponde a otra solicitud. Revisá la operación original.');
                    }
                    if ($existing['result_json'] === null) { throw new HttpError(409, 'operation_incomplete', 'La operación requiere revisión.'); }
                    $result = json_decode($existing['result_json'], true, 32, JSON_THROW_ON_ERROR);
                    $db->commit();
                    return $result + ['replayed' => true];
                }
                $query = $db->prepare('INSERT INTO operations (actor_user_id, scope, idempotency_key, request_hash, secret_hash) VALUES (?, ?, ?, ?, ?)');
                $query->execute([$actor['id'], $scope, $key, $hash, $secret === null ? null : password_hash($secret,PASSWORD_DEFAULT)]);
                $operationId = $db->lastInsertId();
                $result = $write($operationId) + ['operation_id' => $operationId];
                $query = $db->prepare('UPDATE operations SET result_json = ? WHERE id = ?');
                $query->execute([json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $operationId]);
                $db->commit();
                return $result + ['replayed' => false];
            } catch (\Throwable $error) {
                if ($db->inTransaction()) { $db->rollBack(); }
                if ($error instanceof PDOException && in_array((int)($error->errorInfo[1] ?? 0), [1205, 1213], true) && $attempt < 2) {
                    usleep(random_int(20000, 60000));
                    continue;
                }
                throw $error;
            }
        }
        throw new HttpError(409, 'busy', 'La operación está ocupada. Reintentá con la misma clave.');
    }
}
