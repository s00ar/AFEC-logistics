<?php
declare(strict_types=1);

// Hostinger deploys the whole repository as the document root.
define('AFEC_REPOSITORY_WEBROOT', true);
require __DIR__ . '/public/index.php';
