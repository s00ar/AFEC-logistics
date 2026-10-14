<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

use Afec\Repositories\UserRepository;
use Afec\Services\RecipeCatalogue;
use Afec\Services\RecipeCatalogueService;

$app = null;
try {
    $options = getopt('', ['user:', 'apply', 'allow-production']);
    if (!isset($options['user']) || !is_string($options['user'])) { throw new RuntimeException('Usage: php tools/seed-recipes.php --user=ADMIN [--apply] [--allow-production]'); }
    $app = require dirname(__DIR__) . '/src/bootstrap.php';
    $row = (new UserRepository($app->database))->findByUsername($options['user']);
    if (!$row || !$row['active'] || $row['role'] !== 'admin') { throw new RuntimeException('An active administrator is required.'); }
    $catalogue = new RecipeCatalogue(dirname(__DIR__) . '/resources/catalogue');
    $service = new RecipeCatalogueService($app->database, $catalogue);
    if (!isset($options['apply'])) {
        echo json_encode(['dry_run' => true, 'data' => $service->preview()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
        exit;
    }
    if ($app->config['app']['environment'] === 'production' && !isset($options['allow-production'])) { throw new RuntimeException('Production requires --allow-production and an authorized backup.'); }
    $result = $service->import(UserRepository::publicUser($row), (int)$row['auth_version'], 'catalogue-' . $catalogue->hash);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    if ($app !== null) { $app->logError($error, bin2hex(random_bytes(8))); }
    fwrite(STDERR, $error instanceof Afec\Http\HttpError ? $error->getMessage() . PHP_EOL : "No se pudo preparar/cargar el catálogo. Revisá el usuario administrador, configuración, migraciones y fuentes.\n");
    exit(1);
}
