<?php
declare(strict_types=1);
namespace Afec\Repositories;

/** Private notification queue. One atomically replaced record per existing account. */
final class PasswordHelpRepository
{
    private readonly string $directory;

    public function __construct(string $storagePath)
    {
        $this->directory = $storagePath . '/password-help';
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('No se pudo preparar la cola privada de ayuda.');
        }
    }

    public function find(string $userId): ?array
    {
        return $this->locked($userId, fn(): ?array => $this->read($userId));
    }

    public function all(): array
    {
        $records = [];
        foreach (glob($this->directory . '/*.json') ?: [] as $path) {
            $id = basename($path, '.json');
            if (preg_match('/^[1-9][0-9]{0,18}$/D', $id)) {
                $record = $this->find($id);
                if ($record !== null) { $records[] = $record; }
            }
        }
        return $records;
    }

    public function request(string $userId, string $now, ?string $resolvedAt): void
    {
        $this->locked($userId, function() use ($userId, $now, $resolvedAt): void {
            $existing = $this->read($userId);
            if ($existing !== null && ($resolvedAt === null || $existing['requested_at'] > $resolvedAt)) { return; }
            $record = ['request_id' => bin2hex(random_bytes(16)), 'user_id' => $userId, 'requested_at' => $now];
            $temporary = tempnam($this->directory, '.request-');
            if ($temporary === false) { throw new \RuntimeException('No se pudo guardar la solicitud.'); }
            try {
                chmod($temporary, 0600);
                $json = json_encode($record, JSON_THROW_ON_ERROR);
                if (file_put_contents($temporary, $json) !== strlen($json) || !rename($temporary, $this->directory . '/' . $userId . '.json')) {
                    throw new \RuntimeException('No se pudo guardar la solicitud.');
                }
            } finally {
                if (is_file($temporary)) { unlink($temporary); }
            }
        });
    }

    private function locked(string $userId, callable $work): mixed
    {
        if (!preg_match('/^[1-9][0-9]{0,18}$/D', $userId)) { throw new \InvalidArgumentException('Cuenta no válida.'); }
        $lock = fopen($this->directory . '/' . $userId . '.lock', 'c+b');
        if ($lock === false) { throw new \RuntimeException('No se pudo abrir la cola privada.'); }
        try {
            if (!flock($lock, LOCK_EX)) { throw new \RuntimeException('No se pudo bloquear la cola privada.'); }
            chmod($this->directory . '/' . $userId . '.lock', 0600);
            return $work();
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private function read(string $userId): ?array
    {
        $path = $this->directory . '/' . $userId . '.json';
        if (!is_file($path)) { return null; }
        $record = json_decode(file_get_contents($path), true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($record) || ($record['user_id'] ?? null) !== $userId || !is_string($record['request_id'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D', $record['request_id']) || !is_string($record['requested_at'] ?? null) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}$/D', $record['requested_at'])) {
            throw new \RuntimeException('La solicitud privada requiere revisión.');
        }
        return $record;
    }
}
