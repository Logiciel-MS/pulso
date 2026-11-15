<?php

declare(strict_types=1);

// php bin/sites.php  -> lists every site

$pdo = require __DIR__ . '/bootstrap.php';

$sites = (new Pulso\Sites($pdo))->all();
if (!$sites) {
    echo "No hay sitios todavía. Crea uno con: php bin/site-add.php \"Nombre\" dominio.com\n";
    exit;
}
foreach ($sites as $site) {
    printf("%-22s %-28s %s\n", $site['public_id'], $site['domain'], $site['name']);
}
