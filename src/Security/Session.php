<?php
declare(strict_types=1);
namespace Afec\Security;

use Afec\Http\HttpError;

final class Session
{
    public function __construct(private readonly array $config, string $storagePath, string $basePath)
    {
        $directory = $storagePath . '/sessions';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('No se pudo preparar el almacenamiento de sesiones.');
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) $config['absolute_seconds']);
        session_save_path($directory);
        session_name($config['name']);
        session_set_cookie_params([
            'lifetime' => 0, 'path' => $basePath === '' ? '/' : $basePath . '/',
            'secure' => $config['cookie_secure'], 'httponly' => true, 'samesite' => 'Lax',
        ]);
        if (!session_start()) {
            throw new \RuntimeException('No se pudo iniciar la sesión.');
        }
        $now = time();
        if (isset($_SESSION['user_id']) && (
            $now - ($_SESSION['last_seen'] ?? 0) >= $config['idle_seconds'] ||
            $now - ($_SESSION['authenticated_at'] ?? 0) >= $config['absolute_seconds']
        )) {
            $this->logout();
        }
        $_SESSION['last_seen'] = $now;
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public function csrf(): string { return $_SESSION['csrf']; }
    public function userId(): ?string { return isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : null; }
    public function authVersion(): ?int { return $_SESSION['auth_version'] ?? null; }

    public function verifyCsrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($token) || !hash_equals($this->csrf(), $token)) {
            throw new HttpError(403, 'csrf', 'La sesión del formulario cambió. Recargá e intentá de nuevo.');
        }
    }

    public function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION = [
            'user_id' => (string) $user['id'], 'auth_version' => (int) $user['auth_version'],
            'authenticated_at' => time(), 'last_seen' => time(), 'csrf' => bin2hex(random_bytes(32)),
        ];
    }

    public function logout(): void
    {
        $_SESSION = ['csrf' => bin2hex(random_bytes(32)), 'last_seen' => time()];
        session_regenerate_id(true);
    }
}
