<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
$app = require dirname(__DIR__) . '/src/bootstrap.php';
$config = $app->config;
if ($config['app']['environment'] !== 'test' || !in_array($config['database']['host'], ['127.0.0.1', 'localhost'], true) || !str_starts_with($config['database']['name'], 'afec_test_')) { throw new RuntimeException('Solo bases locales de pruebas.'); }
$jobPath = $argv[1] ?? '';
$job = json_decode(file_get_contents($jobPath), true, 32, JSON_THROW_ON_ERROR);
$users = new Afec\Repositories\UserRepository($app->database);
$user = $users->findById((string)$job['actor_id']);
$actor = Afec\Repositories\UserRepository::publicUser($user);
$service = new Afec\Services\PlanningService(new Afec\Repositories\PlanningRepository($app->database), new Afec\Services\MutationService($app->database));
file_put_contents($jobPath . '.ready', 'ready');
$deadline = microtime(true) + 5;
while (!is_file($job['barrier'])) {
    if (microtime(true) > $deadline) { throw new RuntimeException('Concurrency barrier timed out.'); }
    usleep(10000);
}
try {
    $result = match ($job['action']) {
        'create' => $service->createSystem($actor, (int)$user['auth_version'], $job['key'], $job['body']),
        'update' => $service->updateSystem($actor, (int)$user['auth_version'], $job['key'], $job['id'], $job['body']),
        'requirement' => $service->saveRequirement($actor, (int)$user['auth_version'], $job['key'], $job['installation_id'], $job['commodity_id'], $job['body']),
    };
    echo json_encode($result, JSON_THROW_ON_ERROR);
} catch (Afec\Http\HttpError $error) {
    echo json_encode(['status' => $error->status, 'error' => $error->errorCode], JSON_THROW_ON_ERROR);
}
