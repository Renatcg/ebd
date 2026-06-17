<?php

declare(strict_types=1);

final class ClassRepository
{
    public function all(): array
    {
        return Database::connection()
            ->query(
                'SELECT classes.id, classes.course_id, classes.name, classes.description, classes.active,
                        classes.created_at, classes.updated_at, courses.name AS course_name
                 FROM classes
                 INNER JOIN courses ON courses.id = classes.course_id
                 ORDER BY courses.name, classes.name'
            )
            ->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT classes.id, classes.course_id, classes.name, classes.description, classes.active,
                    classes.created_at, classes.updated_at, courses.name AS course_name
             FROM classes
             INNER JOIN courses ON courses.id = classes.course_id
             WHERE classes.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $class = $stmt->fetch();

        return $class ?: null;
    }

    public function create(array $data): array
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO classes (course_id, name, description, active) VALUES (:course_id, :name, :description, :active)'
        );
        $stmt->execute([
            'course_id' => (int) $data['course_id'],
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
            'UPDATE classes
             SET course_id = :course_id, name = :name, description = :description, active = :active,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'course_id' => (int) $data['course_id'],
            'name' => trim($data['name']),
            'description' => trim($data['description'] ?? ''),
            'active' => !empty($data['active']) ? 1 : 0,
        ]);

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = Database::connection()->prepare('DELETE FROM classes WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
