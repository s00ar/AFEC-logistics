<?php
declare(strict_types=1);
namespace Afec\Repositories;

use Afec\Database;
use Afec\Security\UserRole;

final class UserRepository
{
    public function __construct(private readonly Database $database) {}

    public function findByUsername(string $username): ?array
    {
        $query = $this->database->connection()->prepare('SELECT id, username, display_name, password_hash, role, active, auth_version, commander_id, version, deleted_at FROM users WHERE username = ?');
        $query->execute([$username]);
        return $query->fetch() ?: null;
    }

    public function findById(string $id): ?array
    {
        $query = $this->database->connection()->prepare('SELECT id, username, display_name, role, active, auth_version, commander_id, version, deleted_at FROM users WHERE id = ?');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }

    public static function publicUser(array $user): array
    {
        return [
            'id' => (string) $user['id'], 'username' => $user['username'],
            'display_name' => $user['display_name'], 'role' => UserRole::canonical($user['role']),
            'commander_id' => $user['commander_id'] === null ? null : (string) $user['commander_id'],
        ];
    }

    public function list(): array
    {
        return $this->listing()['items'];
    }

    public function listing(int $page = 1): array
    {
        $query = $this->database->connection()->query('SELECT id, username, display_name, role, active, commander_id, version, deleted_at FROM users ORDER BY username LIMIT 26 OFFSET ' . (($page - 1) * 25));
        $rows = $query->fetchAll();
        $more = count($rows) > 25;
        if ($more) { array_pop($rows); }
        return ['items' => array_map(self::adminView(...), $rows), 'page' => $page, 'has_more' => $more];
    }

    public static function adminView(array $row): array
    {
        return self::publicUser($row) + ['active' => (bool)$row['active'], 'version' => (string)$row['version'], 'deleted_at' => $row['deleted_at']];
    }
}
