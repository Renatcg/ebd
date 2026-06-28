<?php

declare(strict_types=1);

final class LessonRepository
{
    public function markersForMonth(string $month): array
    {
        $start = DateTimeImmutable::createFromFormat('Y-m-d', $month . '-01');

        if (!$start || $start->format('Y-m') !== $month) {
            Response::error('Informe um mes valido.', 422);
        }

        $end = $start->modify('first day of next month');
        $stmt = Database::connection()->prepare(
            'SELECT class_id, lesson_date
             FROM lessons
             WHERE lesson_date >= :start AND lesson_date < :end
             ORDER BY lesson_date, class_id'
        );
        $stmt->execute([
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ]);

        return $stmt->fetchAll();
    }

    public function getWorkArea(int $classId, string $lessonDate): array
    {
        $this->validateSunday($lessonDate);
        $class = (new ClassRepository())->find($classId);

        if ($class === null) {
            Response::error('Classe nao encontrada.', 404);
        }

        $people = (new ClassPeopleRepository())->get($classId);
        $lesson = $this->findByClassAndDate($classId, $lessonDate);
        $attendance = [];

        if ($lesson !== null) {
            $attendance = $this->attendanceFor((int) $lesson['id']);
        }

        return [
            'class' => $class,
            'teachers' => $people['teachers'],
            'students' => $people['students'],
            'lesson' => $lesson,
            'attendance' => $attendance,
        ];
    }

    public function save(array $data): array
    {
        $classId = (int) $data['class_id'];
        $lessonDate = trim((string) $data['lesson_date']);
        $teacherId = (int) $data['teacher_person_id'];
        $title = trim((string) $data['title']);
        $notes = $this->nullableText($data['notes'] ?? null);
        $attendance = is_array($data['attendance'] ?? null) ? $data['attendance'] : [];

        $this->validateSunday($lessonDate);

        if ($title === '') {
            Response::error('Informe a aula ministrada.', 422);
        }

        $people = (new ClassPeopleRepository())->get($classId);
        $teacherIds = array_map(fn (array $person): int => (int) $person['id'], $people['teachers']);
        $studentIds = array_map(fn (array $person): int => (int) $person['id'], $people['students']);

        if (!in_array($teacherId, $teacherIds, true)) {
            Response::error('Selecione um professor vinculado a esta classe.', 422);
        }

        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $lesson = $this->findByClassAndDate($classId, $lessonDate);

            if ($lesson === null) {
                $stmt = $pdo->prepare(
                    'INSERT INTO lessons (class_id, lesson_date, title, teacher_person_id, notes)
                     VALUES (:class_id, :lesson_date, :title, :teacher_person_id, :notes)'
                );
                $stmt->execute([
                    'class_id' => $classId,
                    'lesson_date' => $lessonDate,
                    'title' => $title,
                    'teacher_person_id' => $teacherId,
                    'notes' => $notes,
                ]);
                $lessonId = (int) Database::lastInsertId('lessons');
            } else {
                $lessonId = (int) $lesson['id'];
                $stmt = $pdo->prepare(
                    'UPDATE lessons
                     SET title = :title, teacher_person_id = :teacher_person_id, notes = :notes,
                         updated_at = CURRENT_TIMESTAMP
                     WHERE id = :id'
                );
                $stmt->execute([
                    'id' => $lessonId,
                    'title' => $title,
                    'teacher_person_id' => $teacherId,
                    'notes' => $notes,
                ]);
            }

            $delete = $pdo->prepare('DELETE FROM attendance WHERE lesson_id = :lesson_id');
            $delete->execute(['lesson_id' => $lessonId]);

            $insert = $pdo->prepare(
                'INSERT INTO attendance (lesson_id, student_person_id, status, notes)
                 VALUES (:lesson_id, :student_person_id, :status, :notes)'
            );

            foreach ($attendance as $row) {
                $studentId = (int) ($row['student_person_id'] ?? 0);
                $status = (string) ($row['status'] ?? 'ausente');

                if (!in_array($studentId, $studentIds, true)) {
                    Response::error('A chamada possui aluno nao vinculado a esta classe.', 422);
                }

                if (!in_array($status, ['presente', 'ausente'], true)) {
                    Response::error('Status de presenca invalido.', 422);
                }

                $insert->execute([
                    'lesson_id' => $lessonId,
                    'student_person_id' => $studentId,
                    'status' => $status,
                    'notes' => $this->nullableText($row['notes'] ?? null),
                ]);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        return $this->getWorkArea($classId, $lessonDate);
    }

    private function findByClassAndDate(int $classId, string $lessonDate): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT lessons.id, lessons.class_id, lessons.lesson_date, lessons.title,
                    lessons.teacher_person_id, lessons.notes, people.name AS teacher_name
             FROM lessons
             INNER JOIN people ON people.id = lessons.teacher_person_id
             WHERE lessons.class_id = :class_id AND lessons.lesson_date = :lesson_date'
        );
        $stmt->execute([
            'class_id' => $classId,
            'lesson_date' => $lessonDate,
        ]);
        $lesson = $stmt->fetch();

        return $lesson ?: null;
    }

    private function attendanceFor(int $lessonId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT student_person_id, status, notes
             FROM attendance
             WHERE lesson_id = :lesson_id'
        );
        $stmt->execute(['lesson_id' => $lessonId]);
        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $rows[(int) $row['student_person_id']] = $row;
        }

        return $rows;
    }

    private function validateSunday(string $lessonDate): void
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $lessonDate);

        if (!$date || $date->format('Y-m-d') !== $lessonDate) {
            Response::error('Informe uma data valida.', 422);
        }

        if ($date->format('w') !== '0') {
            Response::error('As aulas do curso biblico devem ser cadastradas em domingos.', 422);
        }
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
