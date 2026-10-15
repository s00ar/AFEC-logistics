<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
$app = require dirname(__DIR__) . '/src/bootstrap.php';
$config = $app->config;
if ($config['app']['environment'] !== 'test' || !in_array($config['database']['host'], ['127.0.0.1', 'localhost'], true) || !str_starts_with($config['database']['name'], 'afec_test_')) { throw new RuntimeException('Solo bases locales de pruebas.'); }
if (($argv[1] ?? '') === 'audit') {
    $q = $app->database->connection()->query("SELECT a.changes_json FROM audit_log a JOIN users u ON u.id=a.entity_id WHERE a.entity_type='user' AND u.username='operator' AND a.action='update' ORDER BY a.id DESC LIMIT 1");
    echo $q->fetchColumn(); exit;
}
$users = new Afec\Repositories\UserRepository($app->database);
$legacy = $users->findByUsername('operator');
$actor = Afec\Repositories\UserRepository::publicUser($legacy);
$actor['role'] = 'admin'; // A forged caller role must never override the locked database row.
$mutations = new Afec\Services\MutationService($app->database);
$result = [
    'canonical' => Afec\Security\UserRole::canonical('operator') === 'viewer' && Afec\Security\UserRole::canonical('viewer') === 'viewer' && Afec\Security\UserRole::canonical('admin') === 'admin' && Afec\Security\UserRole::canonical('unknown') === null,
    'legacy_preserved' => $legacy['role'] === 'operator',
];
foreach (['forged_admin' => [['admin'], (int)$legacy['auth_version']], 'stale_auth' => [['admin','viewer'], (int)$legacy['auth_version'] - 1]] as $name => [$roles, $version]) {
    try {
        $mutations->run($actor, $version, $roles, 'admin.user.create', bin2hex(random_bytes(16)), [], static function(): array { throw new RuntimeException('Unexpected authorized mutation.'); });
    } catch (Afec\Http\HttpError $error) { $result[$name] = $error->status; }
}
echo json_encode($result, JSON_THROW_ON_ERROR);
