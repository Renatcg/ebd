<?php

declare(strict_types=1);

final class CourseRepository
{
    public function all(): array
    {
        return Database::connection()
            ->query('SELECT id, name, description, active, created_at, updated_at FROM courses ORDER BY name')
            ->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, description, active, created_at, updated_at FROM courses WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $course = $stmt->fetch();

        return $course ?: null;
    }

    public function create(array $data): array
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO courses (name, description, active) VALUES (:name, :description, :active)'
        );
        $stmt->execute([
            'name' => trim($data['name']),
            'description' => trim($data['description'] ?? ''),
            'active' => !empty($data['active']) ? 1 : 0,
        ]);

        return $this->find((int) Database::connection()->lastInsertId());
    }

    public function update(int $id, array $data): ?array
    {
        if ($this->find($id) === null) {
            return null;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE courses SET name = :name, description = :description, active = :active, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => trim($data['name']),
            'description' => trim($data['description'] ?? ''),
            'active' => !empty($data['active']) ? 1 : 0,
        ]);

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = Database::connection()->prepare('DELETE FROM courses WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
