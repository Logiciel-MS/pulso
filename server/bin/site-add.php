<?php

declare(strict_types=1);

// php bin/site-add.php "Grado Zero" gradozero.co [America/Bogota]

$pdo = require __DIR__ . '/bootstrap.php';

[$script, $name, $domain, $timezone] = $argv + [null, null, null, 'America/Bogota'];
if (!$name || !$domain) {
    fwrite(STDERR, "Uso: php bin/site-add.php \"Nombre del cliente\" dominio.com [America/Bogota]\n");
    exit(1);
}

try {
    $site = (new Pulso\Sites($pdo))->create($name, $domain, $timezone);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}

echo <<<TXT

  Sitio creado: {$name} ({$domain})

  siteId:  {$site['public_id']}
  API key: {$site['api_key']}

  Guarda la API key ahora: no se vuelve a mostrar (solo guardamos su hash).
  El siteId sí es público, va en el código del sitio del cliente.

TXT;
