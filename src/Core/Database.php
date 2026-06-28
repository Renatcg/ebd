<?php

declare(strict_types=1);

final class Database
{
    private static ?PDO $connection = null;
    private static string $driver = 'sqlite';

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            $databaseUrl = Config::databaseUrl();
            $isNewDatabase = false;

            if ($databaseUrl !== null) {
                self::$driver = 'pgsql';
                self::$connection = new PDO(
                    self::postgresDsn($databaseUrl),
                    self::postgresUser($databaseUrl),
                    self::postgresPassword($databaseUrl)
                );
            } else {
                self::$driver = 'sqlite';
                $databasePath = Config::databasePath();
                $isNewDatabase = !is_file($databasePath);

                if (!is_dir(dirname($databasePath))) {
                    mkdir(dirname($databasePath), 0775, true);
                }

                self::$connection = new PDO('sqlite:' . $databasePath);
            }

            self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            if (self::$driver === 'sqlite') {
                self::$connection->exec('PRAGMA foreign_keys = ON');
            }

            if ($isNewDatabase || self::$driver === 'pgsql') {
                self::initialize(self::$connection);
            }
        }

        return self::$connection;
    }

    public static function driver(): string
    {
        self::connection();

        return self::$driver;
    }

    public static function lastInsertId(string $table): int
    {
        $connection = self::connection();

        if (self::$driver === 'pgsql') {
            return (int) $connection->lastInsertId($table . '_id_seq');
        }

        return (int) $connection->lastInsertId();
    }

    private static function initialize(PDO $connection): void
    {
        $schemaFile = self::$driver === 'pgsql' ? 'schema.postgres.sql' : 'schema.sql';
        $schemaPath = dirname(__DIR__, 2) . '/database/' . $schemaFile;
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

    private static function postgresDsn(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
            throw new RuntimeException('DATABASE_URL invalida.');
        }

        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        $sslMode = (string) ($query['sslmode'] ?? 'require');

        return sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;sslmode=%s',
            $parts['host'],
            (int) ($parts['port'] ?? 5432),
            ltrim($parts['path'], '/'),
            $sslMode
        );
    }

    private static function postgresUser(string $url): ?string
    {
        $parts = parse_url($url);

        return isset($parts['user']) ? urldecode((string) $parts['user']) : null;
    }

    private static function postgresPassword(string $url): ?string
    {
        $parts = parse_url($url);

        return isset($parts['pass']) ? urldecode((string) $parts['pass']) : null;
    }
}
