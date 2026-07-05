<?php

declare(strict_types=1);

final class PersonRepository
{
    public function all(): array
    {
        return Database::connection()
            ->query(
                'SELECT id, name, email, phone, birth_date, notes, created_at, updated_at
                 FROM people
                 ORDER BY name'
            )
            ->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, email, phone, birth_date, notes, created_at, updated_at
             FROM people
             WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $person = $stmt->fetch();

        return $person ?: null;
    }

    public function findByName(string $name): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, email, phone, birth_date, notes, created_at, updated_at
             FROM people
             WHERE LOWER(name) = LOWER(:name)
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

        return $this->find((int) Database::lastInsertId('people'));
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

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = Database::connection()->prepare('DELETE FROM people WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private function payload(array $data): array
    {
        return [
            'name' => trim($data['name']),
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
}
