<?php

declare(strict_types=1);

// php bin/migrate.php  -> creates the pulso_* tables (safe to run again)

$pdo = require __DIR__ . '/bootstrap.php';
Pulso\Database::migrate($pdo);
echo "Tablas listas ✅\n";
