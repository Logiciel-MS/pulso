<?php

declare(strict_types=1);

namespace Pulso\Tests;

use PDO;
use Pulso\Database;

/**
 * Tiny test base. No PHPUnit on purpose: Pulso has zero dependencies and the
 * tests can run anywhere PHP runs, including the shared hosting itself.
 */
abstract class TestCase
{
    public int $assertions = 0;

    public function setUp(): void
    {
    }

    /**
     * Fresh database per test. PULSO_TEST_DSN picks MySQL/MariaDB, default is SQLite in memory.
     */
    protected function db(): PDO
    {
        $dsn = getenv('PULSO_TEST_DSN') ?: 'sqlite::memory:';
        $pdo = Database::connect($dsn, getenv('PULSO_TEST_USER') ?: null, getenv('PULSO_TEST_PASSWORD') ?: null);
        if (!str_starts_with($dsn, 'sqlite')) {
            foreach (['pulso_events', 'pulso_salts', 'pulso_sites'] as $table) {
                $pdo->exec("DROP TABLE IF EXISTS {$table}");
            }
        }
        Database::migrate($pdo);
        return $pdo;
    }

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            throw new AssertionFailed(
                ($message ? "{$message}\n" : '') . 'Expected ' . var_export($expected, true) . "\n     got " . var_export($actual, true),
            );
        }
    }

    protected function assertTrue(bool $condition, string $message = 'Expected true'): void
    {
        $this->assertSame(true, $condition, $message);
    }

    protected function assertFalse(bool $condition, string $message = 'Expected false'): void
    {
        $this->assertSame(false, $condition, $message);
    }

    /** @param callable(): mixed $fn */
    protected function assertThrows(string $class, callable $fn): void
    {
        $this->assertions++;
        try {
            $fn();
        } catch (\Throwable $e) {
            if ($e instanceof $class) {
                return;
            }
            throw new AssertionFailed("Expected {$class}, got " . $e::class . ': ' . $e->getMessage());
        }
        throw new AssertionFailed("Expected {$class}, nothing was thrown");
    }
}

final class AssertionFailed extends \Exception
{
}
