<?php

declare(strict_types=1);

final class StudentReportRepository
{
    public function students(?array $user = null): array
    {
        $classIds = $this->visibleClassIds($user);
        $where = '';
        $params = [];

        if ($classIds !== null) {
            if ($classIds === []) {
                return [];
            }

            $placeholders = implode(',', array_map(fn (int $index): string => ':class_' . $index, array_keys($classIds)));
            $where = " WHERE class_students.class_id IN ({$placeholders})";
            foreach ($classIds as $index => $visibleClassId) {
                $params['class_' . $index] = $visibleClassId;
            }
        }

        $stmt = Database::connection()->prepare(
            "SELECT people.id, people.name, people.email, people.phone, COUNT(class_students.class_id) AS class_count
             FROM people
             INNER JOIN class_students ON class_students.person_id = people.id
             {$where}
             GROUP BY people.id, people.name, people.email, people.phone
             ORDER BY people.name"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function classesForStudent(int $studentId, ?array $user = null): array
    {
        $classIds = $this->visibleClassIds($user);
        $classFilter = '';
        $params = ['student_id' => $studentId];

        if ($classIds !== null) {
            if ($classIds === []) {
                return [];
            }

            $placeholders = implode(',', array_map(fn (int $index): string => ':class_' . $index, array_keys($classIds)));
            $classFilter = " AND classes.id IN ({$placeholders})";
            foreach ($classIds as $index => $visibleClassId) {
                $params['class_' . $index] = $visibleClassId;
            }
        }

        $stmt = Database::connection()->prepare(
            "SELECT classes.id, classes.name, classes.course_id, courses.name AS course_name
             FROM class_students
             INNER JOIN classes ON classes.id = class_students.class_id
             INNER JOIN courses ON courses.id = classes.course_id
             WHERE class_students.person_id = :student_id
             {$classFilter}
             ORDER BY courses.name, classes.name"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function opinionForStudent(int $studentId, array $author): array
    {
        $student = (new PersonRepository())->find($studentId);

        if ($student === null) {
            Response::error('Aluno nao encontrado.', 404);
        }

        $classes = $this->classesForStudent($studentId, $author);
        $reports = array_reverse($this->all(null, $studentId, $author));
        $lines = [];
        $lines[] = 'Parecer pedagogico';
        $lines[] = '';
        $lines[] = 'Aluno: ' . $student['name'];
        $lines[] = 'Data de emissao: ' . date('d/m/Y');
        $lines[] = 'Classes: ' . ($classes === [] ? 'Sem classes vinculadas' : implode(', ', array_map(
            fn (array $class): string => $class['course_name'] . ' / ' . $class['name'],
            $classes
        )));
        $lines[] = '';

        if ($reports === []) {
            $lines[] = 'Nao ha relatorios pedagogicos registrados para este aluno.';
        } else {
            $lines[] = 'Historico analisado: ' . count($reports) . ' relatorio(s).';
            $lines[] = '';
            $lines[] = 'Sintese dos registros:';

            foreach ($reports as $report) {
                $date = DateTimeImmutable::createFromFormat('Y-m-d', $report['report_date']);
                $lines[] = '';
                $lines[] = '- ' . ($date ? $date->format('d/m/Y') : $report['report_date'])
                    . ' | ' . $report['course_name'] . ' / ' . $report['class_name']
                    . ' | ' . $report['title'];
                $lines[] = trim($report['body']);
            }
        }

        $lines[] = '';
        $generated = (new OpenAIOpinionService())->generate($student, $classes, $reports);
        $lines[] = 'Parecer gerado pela IA:';
        $lines[] = $generated['text'];
        $text = implode("\n", $lines);
        $storedOpinion = (new StudentOpinionRepository())->create(
            $studentId,
            (int) $author['id'],
            $generated['model'],
            $generated['prompt'],
            $text,
            count($reports)
        );

        return [
            'student' => $student,
            'classes' => $classes,
            'reports' => $reports,
            'opinion' => $storedOpinion,
            'text' => $text,
        ];
    }

    public function all(?int $classId = null, ?int $studentId = null, ?array $user = null): array
    {
        $where = [];
        $params = [];
        $classIds = $this->visibleClassIds($user);

        if ($classIds !== null) {
            if ($classIds === []) {
                return [];
            }

            $placeholders = implode(',', array_map(fn (int $index): string => ':visible_class_' . $index, array_keys($classIds)));
            $where[] = "student_reports.class_id IN ({$placeholders})";
            foreach ($classIds as $index => $visibleClassId) {
                $params['visible_class_' . $index] = $visibleClassId;
            }
        }

        if ($classId !== null && $classId > 0) {
            $where[] = 'student_reports.class_id = :class_id';
            $params['class_id'] = $classId;
        }

        if ($studentId !== null && $studentId > 0) {
            $where[] = 'student_reports.student_person_id = :student_id';
            $params['student_id'] = $studentId;
        }

        $sql = 'SELECT student_reports.id, student_reports.student_person_id, student_reports.class_id,
                       student_reports.author_user_id, student_reports.title, student_reports.body,
                       student_reports.report_date, student_reports.created_at, student_reports.updated_at,
                       students.name AS student_name, classes.name AS class_name, courses.name AS course_name,
                       users.name AS author_name
                FROM student_reports
                INNER JOIN people students ON students.id = student_reports.student_person_id
                INNER JOIN classes ON classes.id = student_reports.class_id
                INNER JOIN courses ON courses.id = classes.course_id
                INNER JOIN users ON users.id = student_reports.author_user_id';

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY student_reports.report_date DESC, student_reports.id DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function create(array $data, array $author): array
    {
        $this->validateStudentInClass((int) $data['student_person_id'], (int) $data['class_id'], $author);

        $stmt = Database::connection()->prepare(
            'INSERT INTO student_reports (student_person_id, class_id, author_user_id, title, body, report_date)
             VALUES (:student_person_id, :class_id, :author_user_id, :title, :body, :report_date)'
        );
        $stmt->execute([
            'student_person_id' => (int) $data['student_person_id'],
            'class_id' => (int) $data['class_id'],
            'author_user_id' => (int) $author['id'],
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'report_date' => trim($data['report_date']),
        ]);

        return $this->find((int) Database::lastInsertId('student_reports'));
    }

    public function update(int $id, array $data, array $author): ?array
    {
        if ($this->find($id, $author) === null) {
            return null;
        }

        $this->validateStudentInClass((int) $data['student_person_id'], (int) $data['class_id'], $author);

        $stmt = Database::connection()->prepare(
            'UPDATE student_reports
             SET student_person_id = :student_person_id, class_id = :class_id, title = :title,
                 body = :body, report_date = :report_date, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'student_person_id' => (int) $data['student_person_id'],
            'class_id' => (int) $data['class_id'],
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'report_date' => trim($data['report_date']),
        ]);

        return $this->find($id, $author);
    }

    public function delete(int $id): bool
    {
        $stmt = Database::connection()->prepare('DELETE FROM student_reports WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private function find(int $id, ?array $user = null): ?array
    {
        $reports = $this->all(null, null, $user);

        foreach ($reports as $report) {
            if ((int) $report['id'] === $id) {
                return $report;
            }
        }

        return null;
    }

    private function validateStudentInClass(int $studentId, int $classId, array $user): void
    {
        $visibleClassIds = $this->visibleClassIds($user);

        if ($visibleClassIds !== null && !in_array($classId, $visibleClassIds, true)) {
            Response::error('Voce nao tem acesso a esta classe.', 403);
        }

        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM class_students WHERE person_id = :person_id AND class_id = :class_id'
        );
        $stmt->execute([
            'person_id' => $studentId,
            'class_id' => $classId,
        ]);

        if ((int) $stmt->fetchColumn() === 0) {
            Response::error('Selecione um aluno matriculado nesta classe.', 422);
        }
    }

    private function visibleClassIds(?array $user): ?array
    {
        if ($user === null || in_array($user['role'], ['admin', 'pedagogico', 'diretor'], true)) {
            return null;
        }

        if (!in_array($user['role'], ['professor', 'embaixador'], true)) {
            return [];
        }

        return (new ClassPeopleRepository())->staffClassIdsForUser($user);
    }
}
