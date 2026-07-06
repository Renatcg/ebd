<?php

declare(strict_types=1);

final class ClassPeopleRepository
{
    public function get(int $classId): array
    {
        return [
            'students' => $this->peopleFor($classId, 'class_students'),
            'teachers' => $this->peopleFor($classId, 'class_teachers'),
        ];
    }

    public function groupedIds(): array
    {
        $grouped = [];

        foreach (['students' => 'class_students', 'teachers' => 'class_teachers'] as $key => $table) {
            $rows = Database::connection()
                ->query("SELECT class_id, person_id FROM {$table} ORDER BY class_id, person_id")
                ->fetchAll();

            foreach ($rows as $row) {
                $classId = (int) $row['class_id'];
                $grouped[$classId] ??= ['students' => [], 'teachers' => []];
                $grouped[$classId][$key][] = (int) $row['person_id'];
            }
        }

        return $grouped;
    }

    public function sync(int $classId, array $studentIds, array $teacherIds): array
    {
        $studentIds = $this->cleanIds($studentIds);
        $teacherIds = $this->cleanIds($teacherIds);

        if (array_intersect($studentIds, $teacherIds) !== []) {
            Response::error('A mesma pessoa nao pode ser aluno e professor na mesma classe.', 422);
        }

        $class = (new ClassRepository())->find($classId);

        if ($class === null) {
            Response::error('Classe nao encontrada.', 404);
        }

        $this->validatePeopleExist([...$studentIds, ...$teacherIds]);
        $this->validateNoCourseConflict($classId, (int) $class['course_id'], [...$studentIds, ...$teacherIds]);

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $this->replace('class_students', $classId, $studentIds);
            $this->replace('class_teachers', $classId, $teacherIds);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        return $this->get($classId);
    }

    private function peopleFor(int $classId, string $table): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT people.id, people.name, people.email, people.phone
             FROM {$table}
             INNER JOIN people ON people.id = {$table}.person_id
             WHERE {$table}.class_id = :class_id
             ORDER BY people.name"
        );
        $stmt->execute(['class_id' => $classId]);

        return $stmt->fetchAll();
    }

    private function replace(string $table, int $classId, array $personIds): void
    {
        $delete = Database::connection()->prepare("DELETE FROM {$table} WHERE class_id = :class_id");
        $delete->execute(['class_id' => $classId]);

        $insert = Database::connection()->prepare(
            "INSERT INTO {$table} (class_id, person_id) VALUES (:class_id, :person_id)"
        );

        foreach ($personIds as $personId) {
            $insert->execute([
                'class_id' => $classId,
                'person_id' => $personId,
            ]);
        }
    }

    private function cleanIds(array $ids): array
    {
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, fn (int $id): bool => $id > 0);

        return array_values(array_unique($ids));
    }

    private function validatePeopleExist(array $personIds): void
    {
        if ($personIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($personIds), '?'));
        $stmt = Database::connection()->prepare("SELECT COUNT(*) FROM people WHERE id IN ({$placeholders})");
        $stmt->execute($personIds);

        if ((int) $stmt->fetchColumn() !== count($personIds)) {
            Response::error('Uma ou mais pessoas selecionadas nao foram encontradas.', 422);
        }
    }

    private function validateNoCourseConflict(int $classId, int $courseId, array $personIds): void
    {
        if ($personIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($personIds), '?'));
        $sql = "
            SELECT people.name
            FROM people
            WHERE people.id IN ({$placeholders})
              AND EXISTS (
                  SELECT 1
                  FROM classes
                  LEFT JOIN class_students ON class_students.class_id = classes.id
                  LEFT JOIN class_teachers ON class_teachers.class_id = classes.id
                  WHERE classes.course_id = ?
                    AND classes.id <> ?
                    AND (class_students.person_id = people.id OR class_teachers.person_id = people.id)
              )
            ORDER BY people.name
            LIMIT 1
        ";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([...$personIds, $courseId, $classId]);
        $name = $stmt->fetchColumn();

        if ($name !== false) {
            Response::error("{$name} ja esta vinculado a outra classe deste curso.", 422);
        }
    }
}
