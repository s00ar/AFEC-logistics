<?php
declare(strict_types=1);

namespace Afec;

use PDO;
use RuntimeException;

final class Database
{
    private ?PDO $connection = null;

    public function __construct(private readonly array $config) {}

    public function connection(): PDO
    {
        if ($this->connection !== null) {
            return $this->connection;
        }
        foreach (['host', 'name', 'username'] as $key) {
            $value = $this->config[$key] ?? '';
            if (!is_string($value) || $value === '' || preg_match('/[;\x00-\x1f]/', $value)) {
                throw new RuntimeException('Falta una configuración MySQL válida.');
            }
        }
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('Se necesita la extensión pdo_mysql.');
        }
        $this->connection = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $this->config['host'], $this->config['port'], $this->config['name']),
            $this->config['username'], $this->config['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 5]
        );
        $this->connection->exec("SET time_zone = '+00:00'");
        return $this->connection;
    }
}
