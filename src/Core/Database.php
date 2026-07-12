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

            self::initialize(self::$connection);
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
        self::migrate($connection);

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

    private static function migrate(PDO $connection): void
    {
        self::migrateUsers($connection);
        self::createRoleTable($connection, 'class_ambassadors');
    }

    private static function migrateUsers(PDO $connection): void
    {
        if (self::$driver === 'pgsql') {
            $constraint = self::constraintDefinition($connection, 'users', 'users_role_check');

            if (
                $constraint === null
                || !str_contains($constraint, "'embaixador'::text")
                || !str_contains($constraint, "'diretor'::text")
            ) {
                $connection->exec('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');

                try {
                    $connection->exec(
                        "ALTER TABLE users ADD CONSTRAINT users_role_check
                         CHECK (role IN ('admin', 'secretaria', 'professor', 'pedagogico', 'embaixador', 'diretor'))"
                    );
                } catch (PDOException $exception) {
                    if ($exception->getCode() !== '42710') {
                        throw $exception;
                    }
                }
            }

            if (!self::columnExists($connection, 'users', 'person_id')) {
                $connection->exec('ALTER TABLE users ADD COLUMN person_id INTEGER UNIQUE');
            }

            return;
        }

        if (!self::columnExists($connection, 'users', 'person_id')) {
            $connection->exec('ALTER TABLE users ADD COLUMN person_id INTEGER');
        }

        $rows = $connection->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetch();
        $sql = (string) ($rows['sql'] ?? '');

        if (str_contains($sql, "'embaixador'") && str_contains($sql, "'diretor'")) {
            return;
        }

        $connection->exec('PRAGMA foreign_keys = OFF');
        $connection->beginTransaction();

        try {
            $connection->exec('ALTER TABLE users RENAME TO users_old');
            $connection->exec(
                "CREATE TABLE users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    email TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    role TEXT NOT NULL CHECK (role IN ('admin', 'secretaria', 'professor', 'pedagogico', 'embaixador', 'diretor')),
                    person_id INTEGER UNIQUE,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )"
            );
            $connection->exec(
                'INSERT INTO users (id, name, email, password_hash, role, person_id, created_at, updated_at)
                 SELECT id, name, email, password_hash, role, person_id, created_at, updated_at FROM users_old'
            );
            $connection->exec('DROP TABLE users_old');
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        } finally {
            $connection->exec('PRAGMA foreign_keys = ON');
        }
    }

    private static function createRoleTable(PDO $connection, string $table): void
    {
        if (self::$driver === 'pgsql') {
            $connection->exec(
                "CREATE TABLE IF NOT EXISTS {$table} (
                    id SERIAL PRIMARY KEY,
                    class_id INTEGER NOT NULL,
                    person_id INTEGER NOT NULL,
                    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
                    FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE,
                    UNIQUE (class_id, person_id)
                )"
            );
        } else {
            $connection->exec(
                "CREATE TABLE IF NOT EXISTS {$table} (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    class_id INTEGER NOT NULL,
                    person_id INTEGER NOT NULL,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
                    FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE,
                    UNIQUE (class_id, person_id)
                )"
            );
        }

        $connection->exec("CREATE INDEX IF NOT EXISTS idx_{$table}_person ON {$table}(person_id)");
    }

    private static function columnExists(PDO $connection, string $table, string $column): bool
    {
        if (self::$driver === 'pgsql') {
            $stmt = $connection->prepare(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_name = :table AND column_name = :column'
            );
            $stmt->execute(['table' => $table, 'column' => $column]);

            return (int) $stmt->fetchColumn() > 0;
        }

        $stmt = $connection->query("PRAGMA table_info({$table})");

        foreach ($stmt->fetchAll() as $row) {
            if (($row['name'] ?? '') === $column) {
                return true;
            }
        }

        return false;
    }

    private static function constraintDefinition(PDO $connection, string $table, string $constraint): ?string
    {
        $stmt = $connection->prepare(
            "SELECT pg_get_constraintdef(c.oid)
             FROM pg_constraint c
             INNER JOIN pg_class t ON t.oid = c.conrelid
             WHERE t.relname = :table AND c.conname = :constraint
             LIMIT 1"
        );
        $stmt->execute(['table' => $table, 'constraint' => $constraint]);
        $definition = $stmt->fetchColumn();

        return is_string($definition) ? $definition : null;
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
