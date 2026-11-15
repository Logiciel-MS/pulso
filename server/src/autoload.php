<?php

declare(strict_types=1);

/*
 * Tiny PSR-4 autoloader so production doesn't need Composer. Shared hosting
 * (Hostinger and friends) doesn't always let you run it, and Pulso has zero
 * runtime dependencies anyway. Composer is only for running the tests.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'Pulso\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
