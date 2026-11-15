<?php

declare(strict_types=1);

namespace Pulso;

use PDO;

final class Database
{
    public static function connect(string $dsn, ?string $user = null, ?string $password = null): PDO
    {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            // We store UTC and do the timezone math in PHP, so MySQL's tz tables
            // (often missing on shared hosting) are never needed.
            $pdo->exec("SET time_zone = '+00:00'");
        }
        return $pdo;
    }

    /** Runs database/schema.{mysql|sqlite}.sql. Safe to run twice. */
    public static function migrate(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'sqlite' : 'mysql';
        $sql = (string) file_get_contents(dirname(__DIR__) . "/database/schema.{$driver}.sql");
        $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $pdo->exec($statement);
        }
    }
}
