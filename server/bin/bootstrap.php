<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$config = Pulso\Config::load(dirname(__DIR__) . '/config.php');

return Pulso\Database::connect($config->dsn(), $config->dbUser(), $config->dbPassword());
