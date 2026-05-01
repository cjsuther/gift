<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database\Connection;
use Dotenv\Dotenv;

Dotenv::createImmutable(__DIR__ . '/..')->load();

$email    = $_ENV['SUPERADMIN_EMAIL']    ?? null;
$password = $_ENV['SUPERADMIN_PASSWORD'] ?? null;
$name     = $_ENV['SUPERADMIN_NAME']     ?? 'Super Admin';

if (!$email || !$password) {
    fwrite(STDERR, "Faltan SUPERADMIN_EMAIL o SUPERADMIN_PASSWORD en .env\n");
    exit(1);
}

$pdo = Connection::get();

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
if ($stmt->fetch() !== false) {
    echo "Super admin ya existe ({$email}). No se hace nada.\n";
    exit(0);
}

$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare(
    'INSERT INTO users (establishment_id, role, name, email, password_hash, is_active)
     VALUES (NULL, :role, :name, :email, :hash, 1)'
);
$stmt->execute([
    'role'  => 'super_admin',
    'name'  => $name,
    'email' => $email,
    'hash'  => $hash,
]);

echo "Super admin creado: {$email}\n";
