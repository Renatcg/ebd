<?php

declare(strict_types=1);

final class StudentOpinionRepository
{
    public function allForStudent(int $studentId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT student_opinions.id, student_opinions.student_person_id, student_opinions.author_user_id,
                    student_opinions.model, student_opinions.prompt, student_opinions.body,
                    student_opinions.source_report_count, student_opinions.created_at,
                    users.name AS author_name
             FROM student_opinions
             INNER JOIN users ON users.id = student_opinions.author_user_id
             WHERE student_opinions.student_person_id = :student_id
             ORDER BY student_opinions.created_at DESC, student_opinions.id DESC'
        );
        $stmt->execute(['student_id' => $studentId]);

        return $stmt->fetchAll();
    }

    public function create(int $studentId, int $authorUserId, string $model, string $prompt, string $body, int $reportCount): array
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO student_opinions (student_person_id, author_user_id, model, prompt, body, source_report_count)
             VALUES (:student_person_id, :author_user_id, :model, :prompt, :body, :source_report_count)'
        );
        $stmt->execute([
            'student_person_id' => $studentId,
            'author_user_id' => $authorUserId,
            'model' => $model,
            'prompt' => $prompt,
            'body' => $body,
            'source_report_count' => $reportCount,
        ]);

        return $this->find((int) Database::connection()->lastInsertId());
    }

    private function find(int $id): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT student_opinions.id, student_opinions.student_person_id, student_opinions.author_user_id,
                    student_opinions.model, student_opinions.prompt, student_opinions.body,
                    student_opinions.source_report_count, student_opinions.created_at,
                    users.name AS author_name
             FROM student_opinions
             INNER JOIN users ON users.id = student_opinions.author_user_id
             WHERE student_opinions.id = :id'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch();
    }
}
