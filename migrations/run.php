<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;
use Dotenv\Dotenv;

Dotenv::createImmutable(__DIR__ . '/..')->load();

$pdo = Connection::get();

$files = glob(__DIR__ . '/*.sql');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    echo "→ Ejecutando {$name}... ";

    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        echo "vacío, salto.\n";
        continue;
    }

    try {
        $pdo->exec($sql);
        echo "OK\n";
    } catch (\PDOException $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\nMigrations completadas.\n";
