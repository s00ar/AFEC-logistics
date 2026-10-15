<?php
declare(strict_types=1);
namespace Afec\Repositories;

use Afec\Database;

final class AccountRepository
{
    public function __construct(private readonly Database $database) {}

    public function lockUser(string $id): ?array
    {
        $q = $this->database->connection()->prepare('SELECT id,username,display_name,password_hash,active,role,auth_version,version,commander_id,deleted_at FROM users WHERE id=? FOR UPDATE');
        $q->execute([$id]);
        return $q->fetch() ?: null;
    }

    public function resolution(string $id): ?string
    {
        $q = $this->database->connection()->prepare("SELECT MAX(recorded_at) FROM audit_log WHERE entity_type='user' AND entity_id=? AND action IN ('password','password_help_closed')");
        $q->execute([$id]);
        return $q->fetchColumn() ?: null;
    }

    public function changePassword(string $id, #[\SensitiveParameter] string $password): void
    {
        $q = $this->database->connection()->prepare('UPDATE users SET password_hash=?,auth_version=auth_version+1,version=version+1,updated_at=UTC_TIMESTAMP(6) WHERE id=?');
        $q->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }

    public function pendingUsers(array $requests): array
    {
        $result = [];
        // Bound each query, but retain every registered account and paginate the final queue.
        foreach (array_chunk($requests, 200) as $chunk) {
            $byId = array_column($chunk, null, 'user_id');
            $q = $this->database->connection()->prepare("SELECT u.id,u.username,u.display_name,u.role,u.active,u.commander_id,u.version,u.deleted_at,MAX(a.recorded_at) AS resolved_at FROM users u LEFT JOIN audit_log a ON a.entity_type='user' AND a.entity_id=u.id AND a.action IN ('password','password_help_closed') WHERE u.active=1 AND u.deleted_at IS NULL AND u.id IN (" . implode(',', array_fill(0, count($byId), '?')) . ') GROUP BY u.id,u.username,u.display_name,u.role,u.active,u.commander_id,u.version,u.deleted_at');
            $q->execute(array_keys($byId));
            foreach ($q->fetchAll() as $row) {
                $request = $byId[$row['id']];
                if ($row['resolved_at'] === null || $request['requested_at'] > $row['resolved_at']) {
                    $result[] = UserRepository::adminView($row) + $request;
                }
            }
        }
        usort($result, static fn(array $a, array $b): int => [$a['requested_at'], $a['user_id']] <=> [$b['requested_at'], $b['user_id']]);
        return $result;
    }
}
