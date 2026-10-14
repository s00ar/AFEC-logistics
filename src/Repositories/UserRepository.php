<?php
declare(strict_types=1);
namespace Afec\Repositories;

use Afec\Database;

final class UserRepository
{
    public function __construct(private readonly Database $database) {}

    public function findByUsername(string $username): ?array
    {
        $query = $this->database->connection()->prepare('SELECT id, username, display_name, password_hash, role, active, auth_version, commander_id FROM users WHERE username = ?');
        $query->execute([$username]);
        return $query->fetch() ?: null;
    }

    public function findById(string $id): ?array
    {
        $query = $this->database->connection()->prepare('SELECT id, username, display_name, role, active, auth_version, commander_id FROM users WHERE id = ?');
        $query->execute([$id]);
        return $query->fetch() ?: null;
    }

    public static function publicUser(array $user): array
    {
        return [
            'id' => (string) $user['id'], 'username' => $user['username'],
            'display_name' => $user['display_name'], 'role' => $user['role'],
            'commander_id' => $user['commander_id'] === null ? null : (string) $user['commander_id'],
        ];
    }

    public function list(): array
    {
        $query = $this->database->connection()->query('SELECT id, username, display_name, role, active, auth_version, commander_id FROM users ORDER BY username LIMIT 200');
        return array_map(static fn(array $user): array => self::publicUser($user) + ['active' => (bool) $user['active']], $query->fetchAll());
    }
}
