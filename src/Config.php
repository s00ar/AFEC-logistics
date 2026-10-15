<?php
declare(strict_types=1);

namespace Afec;

use RuntimeException;

final class Config
{
    public static function load(string $root): array
    {
        $config = require $root . '/config/config.example.php';
        $explicit = getenv('AFEC_CONFIG_FILE');
        $path = $explicit !== false && $explicit !== '' ? $explicit : $root . '/config/config.local.php';
        if (is_file($path)) {
            $private = require $path;
            if (!is_array($private)) {
                throw new RuntimeException('La configuración privada debe devolver un array.');
            }
            $config = array_replace_recursive($config, $private);
        } elseif ($explicit !== false && $explicit !== '') {
            throw new RuntimeException('No se encontró el archivo privado de configuración.');
        }

        foreach (['HOST' => 'host', 'NAME' => 'name', 'USER' => 'username', 'PASSWORD' => 'password'] as $env => $key) {
            $value = getenv('AFEC_DB_' . $env);
            if ($value !== false) {
                $config['database'][$key] = $value;
            }
        }
        if (($port = getenv('AFEC_DB_PORT')) !== false) {
            if (!ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
                throw new RuntimeException('AFEC_DB_PORT no es válido.');
            }
            $config['database']['port'] = (int) $port;
        }
        if (($environment = getenv('AFEC_ENV')) !== false) {
            $config['app']['environment'] = $environment;
        }
        $basePath = $config['app']['base_path'];
        if (!is_string($basePath) || ($basePath !== '' && !preg_match('~^/[a-zA-Z0-9/_-]+$~D', $basePath))) {
            throw new RuntimeException('La ruta base no es válida.');
        }
        $config['app']['base_path'] = rtrim($basePath, '/');
        if ($config['app']['environment'] === 'production' && !$config['session']['cookie_secure']) {
            throw new RuntimeException('Producción requiere cookies de sesión Secure y HTTPS.');
        }
        if (!in_array($config['app']['environment'], ['production', 'development', 'test'], true)) {
            throw new RuntimeException('Entorno no válido.');
        }
        foreach (['idle_seconds', 'absolute_seconds', 'remember_seconds'] as $limit) {
            if (!is_int($config['session'][$limit]) || $config['session'][$limit] < 60) {
                throw new RuntimeException('La caducidad de sesión no es válida.');
            }
        }
        return $config;
    }
}
