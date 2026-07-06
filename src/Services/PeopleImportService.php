<?php

declare(strict_types=1);

final class PeopleImportService
{
    public function import(array $file, array $genderDecisions = []): array
    {
        $genderDecisions = $this->normalizeGenderDecisions($genderDecisions);

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::error('Nao foi possivel receber a planilha.', 422);
        }

        $path = (string) ($file['tmp_name'] ?? '');
        $name = (string) ($file['name'] ?? '');

        if ($path === '' || !is_uploaded_file($path)) {
            Response::error('Arquivo de importacao invalido.', 422);
        }

        if (!str_ends_with(mb_strtolower($name), '.csv')) {
            Response::error('Envie uma planilha em CSV.', 422);
        }

        $rows = $this->readCsv($path);
        $conflicts = $this->genderConflicts($rows, $genderDecisions);

        if ($conflicts !== []) {
            return [
                'needs_review' => true,
                'conflicts' => $conflicts,
            ];
        }

        $repository = new PersonRepository();
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $rowNumber = (int) ($row['__row_number'] ?? 0);

            if (isset($genderDecisions[$rowNumber])) {
                $row['gender'] = (string) $genderDecisions[$rowNumber];
            }

            $person = $this->personFromRow($row);

            if ($person === null) {
                $skipped++;
                continue;
            }

            $existing = $repository->findByName($person['name']);

            if ($existing === null) {
                $repository->create($person);
                $created++;
                continue;
            }

            $repository->update((int) $existing['id'], $this->mergePerson($existing, $person));
            $updated++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => count($rows),
        ];
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            Response::error('Nao foi possivel abrir a planilha.', 422);
        }

        $header = null;
        $lineNumber = 0;
        $rows = [];

        while (($data = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            $lineNumber++;
            $data = array_map(fn (string $value): string => trim($this->removeBom($value)), $data);

            if ($header === null) {
                $header = $this->headerMap($data);
                continue;
            }

            $row = [];

            foreach ($header as $index => $key) {
                $row[$key] = $data[$index] ?? '';
            }

            $row['__row_number'] = $lineNumber;
            $rows[] = $row;
        }

        fclose($handle);

        if ($header === null || !in_array('name', $header, true)) {
            Response::error('A planilha precisa ter uma coluna NOME.', 422);
        }

        return $rows;
    }

    private function genderConflicts(array $rows, array $genderDecisions): array
    {
        $conflicts = [];

        foreach ($rows as $row) {
            $rowNumber = (int) ($row['__row_number'] ?? 0);

            if (isset($genderDecisions[$rowNumber])) {
                continue;
            }

            $name = $this->cleanText($row['name'] ?? '');
            $provided = $this->normalizeGender($row['gender'] ?? '');
            $suggested = $this->suggestGenderFromName($name);

            if ($name === '' || $provided === '' || $suggested === '' || $provided === $suggested) {
                continue;
            }

            $conflicts[] = [
                'row_number' => $rowNumber,
                'name' => $name,
                'provided_gender' => $provided,
                'suggested_gender' => $suggested,
            ];
        }

        return $conflicts;
    }

    private function normalizeGenderDecisions(array $genderDecisions): array
    {
        $normalized = [];

        foreach ($genderDecisions as $rowNumber => $gender) {
            $rowNumber = (int) $rowNumber;
            $gender = $this->normalizeGender($gender);

            if ($rowNumber > 0 && $gender !== '') {
                $normalized[$rowNumber] = $gender;
            }
        }

        return $normalized;
    }

    private function headerMap(array $header): array
    {
        $map = [];

        foreach ($header as $index => $column) {
            $normalized = $this->normalizeHeader($column);
            $map[$index] = match ($normalized) {
                'qde', 'codigo', 'cod' => 'code',
                'nome', 'name' => 'name',
                'sexo' => 'gender',
                'data nasc', 'data nascimento', 'nascimento', 'birth date' => 'birth_date',
                'celular', 'telefone', 'phone' => 'phone',
                'observacao', 'observacoes', 'obs' => 'notes',
                default => 'ignored_' . $index,
            };
        }

        return $map;
    }

    private function personFromRow(array $row): ?array
    {
        $name = $this->cleanText($row['name'] ?? '');

        if ($name === '' || mb_strtolower($name) === 'rotulos de linha') {
            return null;
        }

        $notes = [];
        $baseNotes = $this->cleanText($row['notes'] ?? '');

        if ($baseNotes !== '') {
            $notes[] = $baseNotes;
        }

        $code = $this->cleanText($row['code'] ?? '');
        $gender = $this->normalizeGender($row['gender'] ?? '');

        if ($code !== '') {
            $notes[] = 'Codigo: ' . $code;
        }

        if ($gender !== '') {
            $notes[] = 'Sexo: ' . $gender;
        }

        return [
            'name' => $name,
            'email' => '',
            'phone' => $this->cleanText($row['phone'] ?? ''),
            'birth_date' => $this->formatDate($row['birth_date'] ?? ''),
            'notes' => implode("\n", $notes),
        ];
    }

    private function formatDate(string $value): string
    {
        $value = $this->cleanText($value);

        if ($value === '') {
            return '';
        }

        $date = DateTimeImmutable::createFromFormat('d/m/Y', $value);

        if ($date !== false && $date->format('d/m/Y') === $value) {
            return $date->format('Y-m-d');
        }

        return '';
    }

    private function normalizeGender(mixed $value): string
    {
        $value = mb_strtolower($this->normalizeHeader((string) $value));

        return match ($value) {
            'm', 'masc', 'masculino', 'homem' => 'MASCULINO',
            'f', 'fem', 'feminino', 'mulher' => 'FEMININO',
            default => '',
        };
    }

    private function suggestGenderFromName(string $name): string
    {
        $firstName = explode(' ', $this->normalizeHeader($name))[0] ?? '';

        $female = [
            'adriana', 'alessandra', 'ana', 'andrea', 'angela', 'antonia', 'barbara', 'beatriz',
            'bruna', 'camila', 'carla', 'carolina', 'cassia', 'catia', 'claudia', 'cristiane',
            'daniela', 'debora', 'denise', 'edna', 'elaine', 'elisangela', 'eliane', 'elizabeth',
            'elza', 'erica', 'fabiana', 'fatima', 'fernanda', 'flavia', 'francisca', 'gabriela',
            'geovana', 'gisele', 'glaucia', 'helena', 'isabel', 'jaqueline', 'joana', 'julia',
            'juliana', 'karina', 'katia', 'larissa', 'leticia', 'luciana', 'lucia', 'luiza',
            'maria', 'marcia', 'mariana', 'marisa', 'marta', 'monica', 'natalia', 'patricia',
            'paula', 'priscila', 'rafaela', 'raquel', 'regina', 'renata', 'rita', 'roberta',
            'rosangela', 'rose', 'sandra', 'simone', 'sonia', 'sueli', 'tatiana', 'teresa',
            'valeria', 'vanessa', 'vera', 'viviane',
        ];
        $male = [
            'ademir', 'adriano', 'ailton', 'alex', 'alexandre', 'anderson', 'andre', 'antonio',
            'bruno', 'carlos', 'claudio', 'cleber', 'daniel', 'david', 'denilson', 'diego',
            'edson', 'eduardo', 'elias', 'emerson', 'fabio', 'felipe', 'fernando', 'flavio',
            'francisco', 'gabriel', 'george', 'gilberto', 'guilherme', 'gustavo', 'henrique',
            'igor', 'jair', 'joao', 'jorge', 'jose', 'julio', 'leandro', 'leonardo', 'lucas',
            'luciano', 'luiz', 'marcelo', 'marcio', 'marcos', 'mario', 'matheus', 'mauricio',
            'michael', 'paulo', 'pedro', 'rafael', 'renato', 'ricardo', 'roberto', 'rodrigo',
            'rogerio', 'ronaldo', 'sergio', 'thiago', 'valter', 'victor', 'vinicius', 'wagner',
            'wilson',
        ];

        if (in_array($firstName, $female, true)) {
            return 'FEMININO';
        }

        if (in_array($firstName, $male, true)) {
            return 'MASCULINO';
        }

        return '';
    }

    private function mergePerson(array $existing, array $imported): array
    {
        foreach (['email', 'phone', 'birth_date', 'notes'] as $field) {
            if (trim((string) ($imported[$field] ?? '')) === '') {
                $imported[$field] = $existing[$field] ?? '';
            }
        }

        return $imported;
    }

    private function normalizeHeader(string $value): string
    {
        $value = mb_strtolower($this->cleanText($value));
        $value = str_replace(['.', ':'], '', $value);
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function cleanText(mixed $value): string
    {
        return trim($this->removeBom((string) $value));
    }

    private function removeBom(string $value): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    }
}
