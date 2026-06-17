<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$databasePath = $root . '/data/ebd.sqlite';
$schemaPath = $root . '/database/schema.sql';

if (!is_dir(dirname($databasePath))) {
    mkdir(dirname($databasePath), 0775, true);
}

$pdo = new PDO('sqlite:' . $databasePath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec(file_get_contents($schemaPath));

$stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
$stmt->execute(['email' => 'admin@ebd.local']);

if ((int) $stmt->fetchColumn() === 0) {
    $insert = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)'
    );

    $insert->execute([
        'name' => 'Administrador',
        'email' => 'admin@ebd.local',
        'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
        'role' => 'admin',
    ]);
}

echo "Banco inicializado em {$databasePath}\n";
