<?php

declare(strict_types=1);

final class ClassPeopleRepository
{
    private const ROLE_TABLES = [
        'students' => 'class_students',
        'teachers' => 'class_teachers',
        'ambassadors' => 'class_ambassadors',
    ];

    private const COURSE_RESTRICTED_ROLES = ['students', 'teachers'];

    public function get(int $classId): array
    {
        return $this->getRoles($classId, array_keys(self::ROLE_TABLES));
    }

    public function getRoles(int $classId, array $roles): array
    {
        $people = [];
        $roles = array_values(array_intersect(array_keys(self::ROLE_TABLES), $roles));

        foreach ($roles as $role) {
            $people[$role] = $this->peopleFor($classId, self::ROLE_TABLES[$role]);
        }

        return $people;
    }

    public function groupedIds(): array
    {
        $grouped = [];

        foreach (self::ROLE_TABLES as $key => $table) {
            $rows = Database::connection()
                ->query("SELECT class_id, person_id FROM {$table} ORDER BY class_id, person_id")
                ->fetchAll();

            foreach ($rows as $row) {
                $classId = (int) $row['class_id'];
                $grouped[$classId] ??= $this->emptyIds();
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
            Response::error('A mesma pessoa nao pode ocupar duas funcoes na mesma classe.', 422);
        }

        $class = (new ClassRepository())->find($classId);

        if ($class === null) {
            Response::error('Classe nao encontrada.', 404);
        }

        $this->validatePeopleExist([...$studentIds, ...$teacherIds]);
        $this->validateNoCourseConflict($classId, (int) $class['course_id'], [...$studentIds, ...$teacherIds]);

        $this->syncTable('class_students', $classId, $studentIds);
        $this->syncTable('class_teachers', $classId, $teacherIds);

        return $this->get($classId);
    }

    public function syncRole(int $classId, string $role, array $personIds): array
    {
        $personIds = $this->cleanIds($personIds);
        $class = (new ClassRepository())->find($classId);

        if ($class === null) {
            Response::error('Classe nao encontrada.', 404);
        }

        $current = $this->get($classId);
        $currentIds = $this->idsByRole($current);

        if (!isset(self::ROLE_TABLES[$role])) {
            Response::error('Tipo de vinculo invalido.', 422);
        }

        $nextIds = $currentIds;
        $nextIds[$role] = $personIds;

        if ($this->hasRoleOverlap($nextIds)) {
            Response::error('A mesma pessoa nao pode ocupar duas funcoes na mesma classe.', 422);
        }

        $existingIds = $currentIds[$role];
        $newIds = array_values(array_diff($personIds, $existingIds));

        $this->validatePeopleExist($newIds);
        if (in_array($role, self::COURSE_RESTRICTED_ROLES, true)) {
            $this->validateNoCourseConflict($classId, (int) $class['course_id'], $newIds);
        }

        if (!$this->sameIds($existingIds, $personIds)) {
            $this->syncTableChanges(self::ROLE_TABLES[$role], $classId, $existingIds, $personIds);
        }

        return $nextIds;
    }

    public function staffClassIdsForUser(array $user): array
    {
        $personId = (int) ($user['person_id'] ?? 0);

        if ($personId <= 0 || in_array($user['role'], ['admin', 'pedagogico', 'diretor'], true)) {
            return [];
        }

        $tables = match ($user['role']) {
            'professor' => ['class_teachers'],
            'embaixador' => ['class_ambassadors'],
            default => [],
        };

        if ($tables === []) {
            return [];
        }

        $classIds = [];

        foreach ($tables as $table) {
            $stmt = Database::connection()->prepare("SELECT class_id FROM {$table} WHERE person_id = :person_id");
            $stmt->execute(['person_id' => $personId]);

            foreach ($stmt->fetchAll() as $row) {
                $classIds[] = (int) $row['class_id'];
            }
        }

        return array_values(array_unique($classIds));
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

    private function syncTable(string $table, int $classId, array $personIds): void
    {
        $this->deleteMissing($table, $classId, $personIds);
        $this->insertMissing($table, $classId, $personIds);
    }

    private function deleteMissing(string $table, int $classId, array $personIds): void
    {
        if ($personIds === []) {
            $delete = Database::connection()->prepare("DELETE FROM {$table} WHERE class_id = :class_id");
            $delete->execute(['class_id' => $classId]);

            return;
        }

        $placeholders = implode(',', array_fill(0, count($personIds), '?'));
        $delete = Database::connection()->prepare(
            "DELETE FROM {$table} WHERE class_id = ? AND person_id NOT IN ({$placeholders})"
        );
        $delete->execute([$classId, ...$personIds]);
    }

    private function insertMissing(string $table, int $classId, array $personIds): void
    {
        if ($personIds === []) {
            return;
        }

        $insert = Database::connection()->prepare(
            "INSERT INTO {$table} (class_id, person_id)
             SELECT :class_id, :person_id
             WHERE NOT EXISTS (
                 SELECT 1 FROM {$table}
                 WHERE class_id = :existing_class_id AND person_id = :existing_person_id
             )"
        );

        foreach ($personIds as $personId) {
            $insert->execute([
                'class_id' => $classId,
                'person_id' => $personId,
                'existing_class_id' => $classId,
                'existing_person_id' => $personId,
            ]);
        }
    }

    private function syncTableChanges(string $table, int $classId, array $existingIds, array $personIds): void
    {
        $deleteIds = array_values(array_diff($existingIds, $personIds));
        $insertIds = array_values(array_diff($personIds, $existingIds));

        $this->deleteSpecific($table, $classId, $deleteIds);
        $this->insertMissing($table, $classId, $insertIds);
    }

    private function deleteSpecific(string $table, int $classId, array $personIds): void
    {
        if ($personIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($personIds), '?'));
        $delete = Database::connection()->prepare(
            "DELETE FROM {$table} WHERE class_id = ? AND person_id IN ({$placeholders})"
        );
        $delete->execute([$classId, ...$personIds]);
    }

    private function sameIds(array $left, array $right): bool
    {
        sort($left);
        sort($right);

        return $left === $right;
    }

    private function emptyIds(): array
    {
        return [
            'students' => [],
            'teachers' => [],
            'ambassadors' => [],
        ];
    }

    private function idsByRole(array $people): array
    {
        $ids = $this->emptyIds();

        foreach (array_keys(self::ROLE_TABLES) as $role) {
            $ids[$role] = array_map(fn (array $person): int => (int) $person['id'], $people[$role] ?? []);
        }

        return $ids;
    }

    private function hasRoleOverlap(array $idsByRole): bool
    {
        $seen = [];

        foreach ($idsByRole as $ids) {
            foreach ($ids as $id) {
                if (isset($seen[$id])) {
                    return true;
                }

                $seen[$id] = true;
            }
        }

        return false;
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
                    AND (
                        class_students.person_id = people.id
                        OR class_teachers.person_id = people.id
                    )
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
