<?php

declare(strict_types=1);

final class PersonRepository
{
    public function all(): array
    {
        return Database::connection()
            ->query(
                'SELECT people.id, people.name, people.email, people.phone, people.birth_date, people.notes,
                        people.created_at, people.updated_at, users.id AS user_id, users.role AS access_role,
                        users.email AS access_email
                 FROM people
                 LEFT JOIN users ON users.person_id = people.id
                 ORDER BY people.name'
            )
            ->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT people.id, people.name, people.email, people.phone, people.birth_date, people.notes,
                    people.created_at, people.updated_at, users.id AS user_id, users.role AS access_role,
                    users.email AS access_email
             FROM people
             LEFT JOIN users ON users.person_id = people.id
             WHERE people.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $person = $stmt->fetch();

        return $person ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT people.id, people.name, people.email, people.phone, people.birth_date, people.notes,
                    people.created_at, people.updated_at, users.id AS user_id, users.role AS access_role,
                    users.email AS access_email
             FROM people
             LEFT JOIN users ON users.person_id = people.id
             WHERE LOWER(people.name) = LOWER(:name)
             LIMIT 1'
        );
        $stmt->execute(['name' => trim($name)]);
        $person = $stmt->fetch();

        return $person ?: null;
    }

    public function create(array $data): array
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO people (name, email, phone, birth_date, notes)
             VALUES (:name, :email, :phone, :birth_date, :notes)'
        );
        $stmt->execute($this->payload($data));
        $personId = (int) Database::lastInsertId('people');
        $this->syncAccess($personId, $data);

        return $this->find($personId);
    }

    public function update(int $id, array $data): ?array
    {
        if ($this->find($id) === null) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE people
             SET name = :name, email = :email, phone = :phone, birth_date = :birth_date,
                 notes = :notes, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        $payload = $this->payload($data);
        $payload['id'] = $id;
        $stmt->execute($payload);
        $this->syncAccess($id, $data);

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $deleteUser = Database::connection()->prepare('DELETE FROM users WHERE person_id = :person_id');
        $deleteUser->execute(['person_id' => $id]);

        $stmt = Database::connection()->prepare('DELETE FROM people WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function normalizeNames(): int
    {
        $people = Database::connection()
            ->query('SELECT id, name FROM people')
            ->fetchAll();
        $stmt = Database::connection()->prepare(
            'UPDATE people SET name = :name, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $updated = 0;

        foreach ($people as $person) {
            $normalized = NameFormatter::personName($person['name'] ?? '');

            if ($normalized === '' || $normalized === (string) ($person['name'] ?? '')) {
                continue;
            }

            $stmt->execute([
                'id' => (int) $person['id'],
                'name' => $normalized,
            ]);
            $updated++;
        }

        return $updated;
    }

    private function payload(array $data): array
    {
        return [
            'name' => NameFormatter::personName($data['name']),
            'email' => $this->nullableText($data['email'] ?? null),
            'phone' => $this->nullableText($data['phone'] ?? null),
            'birth_date' => $this->nullableText($data['birth_date'] ?? null),
            'notes' => $this->nullableText($data['notes'] ?? null),
        ];
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    private function syncAccess(int $personId, array $data): void
    {
        $role = trim((string) ($data['access_role'] ?? ''));
        $email = trim((string) ($data['access_email'] ?? ($data['email'] ?? '')));
        $password = (string) ($data['access_password'] ?? '');
        $existing = $this->userForPerson($personId);

        if ($role === '') {
            if ($existing !== null) {
                $delete = Database::connection()->prepare('DELETE FROM users WHERE person_id = :person_id');
                $delete->execute(['person_id' => $personId]);
            }

            return;
        }

        if (!in_array($role, ['secretaria', 'professor', 'pedagogico', 'embaixador', 'diretor'], true)) {
            Response::error('Perfil de acesso invalido.', 422);
        }

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Response::error('Informe um e-mail valido para o acesso.', 422);
        }

        if ($existing === null && trim($password) === '') {
            Response::error('Informe uma senha para criar o acesso.', 422);
        }

        $this->ensureEmailAvailable($email, $existing['id'] ?? null);

        if ($existing === null) {
            $insert = Database::connection()->prepare(
                'INSERT INTO users (name, email, password_hash, role, person_id)
                 VALUES (:name, :email, :password_hash, :role, :person_id)'
            );
            $insert->execute([
                'name' => NameFormatter::personName($data['name']),
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'person_id' => $personId,
            ]);

            return;
        }

        $fields = 'name = :name, email = :email, role = :role, updated_at = CURRENT_TIMESTAMP';
        $payload = [
            'id' => (int) $existing['id'],
            'name' => NameFormatter::personName($data['name']),
            'email' => $email,
            'role' => $role,
        ];

        if (trim($password) !== '') {
            $fields .= ', password_hash = :password_hash';
            $payload['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $update = Database::connection()->prepare("UPDATE users SET {$fields} WHERE id = :id");
        $update->execute($payload);
    }

    private function userForPerson(int $personId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE person_id = :person_id');
        $stmt->execute(['person_id' => $personId]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    private function ensureEmailAvailable(string $email, ?int $ignoreUserId): void
    {
        $sql = 'SELECT id FROM users WHERE LOWER(email) = LOWER(:email)';
        $params = ['email' => $email];

        if ($ignoreUserId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreUserId;
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        if ($stmt->fetch() !== false) {
            Response::error('Ja existe usuario com esse e-mail.', 422);
        }
    }
}
