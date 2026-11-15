<?php

declare(strict_types=1);

// php tests/run.php [filter]
// PULSO_TEST_DSN="mysql:host=127.0.0.1;dbname=pulso_test" PULSO_TEST_USER=... PULSO_TEST_PASSWORD=... php tests/run.php

require dirname(__DIR__) . '/src/autoload.php';
require __DIR__ . '/TestCase.php';

$filter = $argv[1] ?? '';
$passed = $failed = $assertions = 0;
$failures = [];

foreach (glob(__DIR__ . '/*Test.php') ?: [] as $file) {
    require $file;
    $class = 'Pulso\\Tests\\' . basename($file, '.php');
    foreach (get_class_methods($class) as $method) {
        if (!str_starts_with($method, 'test') || ($filter && !str_contains("{$class}::{$method}", $filter))) {
            continue;
        }
        $test = new $class();
        try {
            $test->setUp();
            $test->$method();
            $passed++;
            echo '.';
        } catch (Throwable $e) {
            $failed++;
            echo 'F';
            $failures[] = "{$class}::{$method}\n  " . $e::class . ': ' . $e->getMessage()
                . "\n  at " . $e->getFile() . ':' . $e->getLine();
        }
        $assertions += $test->assertions;
    }
}

$driver = str_starts_with((string) getenv('PULSO_TEST_DSN'), 'mysql') ? 'MySQL' : 'SQLite';
echo "\n\n" . implode("\n\n", $failures) . ($failures ? "\n\n" : '');
echo "{$passed} passed, {$failed} failed, {$assertions} assertions ({$driver})\n";
exit($failed > 0 ? 1 : 0);
