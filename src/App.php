<?php
declare(strict_types=1);

namespace Afec;

use Afec\Repositories\UserRepository;
use Afec\Security\Session;
use Afec\Services\AuthService;

final class App
{
    private ?AuthService $auth = null;
    private ?Session $session = null;
    private ?\Afec\Services\AccountService $accounts = null;
    public readonly Database $database;

    public function __construct(public readonly array $config)
    {
        $this->database = new Database($config['database']);
    }

    public function session(): Session
    {
        return $this->session ??= new Session($this->config['session'], $this->config['storage_path'], $this->config['app']['base_path']);
    }

    public function auth(): AuthService
    {
        return $this->auth ??= new AuthService($this->database, new UserRepository($this->database), $this->session());
    }

    public function url(string $path): string
    {
        return $this->config['app']['base_path'] . $path;
    }

    public function accounts(): \Afec\Services\AccountService
    {
        return $this->accounts ??= new \Afec\Services\AccountService($this->database, new \Afec\Repositories\PasswordHelpRepository($this->config['storage_path']));
    }

    public function assetUrl(string $path): string
    {
        $prefix = defined('AFEC_REPOSITORY_WEBROOT') && AFEC_REPOSITORY_WEBROOT ? '/public/assets/' : '/assets/';
        $file = dirname(__DIR__) . '/public/assets/' . $path;
        $version = is_file($file) ? '?v=' . filemtime($file) : '';
        return $this->url($prefix . $path) . $version;
    }

    public function logError(\Throwable $error, string $requestId): void
    {
        // Do not log submitted passwords, SQL parameters, DSNs or raw PDO messages.
        $directory = $this->config['storage_path'] . '/logs';
        if (!is_dir($directory)) {
            @mkdir($directory, 0700, true);
        }
        $entry = json_encode([
            'at' => gmdate('c'), 'request_id' => $requestId,
            'exception' => get_class($error), 'code' => (string) $error->getCode(),
            'file' => basename($error->getFile()), 'line' => $error->getLine(),
        ], JSON_UNESCAPED_SLASHES);
        if (@file_put_contents($directory . '/app.log', $entry . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            error_log('AFEC error ' . $requestId . ' (' . get_class($error) . ')');
        }
    }
}
