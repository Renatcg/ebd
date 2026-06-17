<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $databasePath = Config::databasePath();
            $isNewDatabase = !is_file($databasePath);

            if (!is_dir(dirname($databasePath))) {
                mkdir(dirname($databasePath), 0775, true);
            }

            self::$connection = new PDO('sqlite:' . $databasePath);
            self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$connection->exec('PRAGMA foreign_keys = ON');

            if ($isNewDatabase) {
                self::initialize(self::$connection);
            }
        }

        return self::$connection;
    }

    private static function initialize(PDO $connection): void
    {
        $schemaPath = dirname(__DIR__, 2) . '/database/schema.sql';
        $connection->exec((string) file_get_contents($schemaPath));

        $stmt = $connection->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $stmt->execute(['email' => 'admin@ebd.local']);

        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $insert = $connection->prepare(
            'INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)'
        );

        $insert->execute([
            'name' => 'Administrador',
            'email' => 'admin@ebd.local',
            'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
            'role' => 'admin',
        ]);
    }
}
