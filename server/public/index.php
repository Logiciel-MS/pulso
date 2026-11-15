<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

use Pulso\App;
use Pulso\Config;
use Pulso\Database;
use Pulso\Request;
use Pulso\Response;

try {
    $config = Config::load(dirname(__DIR__) . '/config.php');
    $pdo = Database::connect($config->dsn(), $config->dbUser(), $config->dbPassword());
} catch (Throwable $e) {
    error_log('[pulso] ' . $e->getMessage());
    Response::json(500, ['error' => 'server not configured'])->send();
    exit;
}

(new App($pdo, $config))->handle(Request::fromGlobals($config->trustProxy()))->send();
