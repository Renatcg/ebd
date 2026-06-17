<?php

declare(strict_types=1);

final class OpenAIOpinionService
{
    public function generate(array $student, array $classes, array $reports): array
    {
        $apiKey = $this->apiKey();
        $settings = new SettingsRepository();
        $model = $settings->get('openai_model', 'gpt-5.5') ?: 'gpt-5.5';
        $prompt = $settings->get('openai_opinion_prompt', $this->defaultPrompt()) ?: $this->defaultPrompt();
        $input = $this->buildInput($student, $classes, $reports);

        $curl = curl_init('https://api.openai.com/v1/responses');

        if ($curl === false) {
            Response::error('A extensao curl do PHP nao iniciou corretamente.', 500);
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => $model,
                'reasoning' => ['effort' => 'low'],
                'instructions' => $prompt,
                'input' => $input,
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        if ($raw === false || $status < 200 || $status >= 300) {
            $decodedError = json_decode((string) $raw, true);
            $message = $decodedError['error']['message'] ?? null;
            Response::error($message ?: ($error !== '' ? $error : 'Falha ao gerar parecer pela IA.'), 502);
        }

        $decoded = json_decode((string) $raw, true);
        $text = $this->extractText(is_array($decoded) ? $decoded : []);

        if ($text === '') {
            Response::error('Resposta inesperada ao gerar parecer pela IA.', 502);
        }

        return [
            'text' => $text,
            'model' => $model,
            'prompt' => $prompt,
        ];
    }

    private function apiKey(): string
    {
        $stored = (new SettingsRepository())->get('openai_api_key');
        $apiKey = trim((string) ($stored ?: getenv('OPENAI_API_KEY') ?: ''));

        if ($apiKey === '') {
            Response::error('Configure a chave da OpenAI em Configuracoes.', 500);
        }

        return $apiKey;
    }

    public function defaultPrompt(): string
    {
        return (new SettingsRepository())->defaultOpinionPrompt();
    }

    private function buildInput(array $student, array $classes, array $reports): string
    {
        $classText = $classes === []
            ? 'Sem classes vinculadas.'
            : implode("\n", array_map(
                fn (array $class): string => '- ' . $class['course_name'] . ' / ' . $class['name'],
                $classes
            ));

        $reportText = $reports === []
            ? 'Nao ha relatorios registrados.'
            : implode("\n\n", array_map(
                fn (array $report): string => 'Data: ' . $report['report_date']
                    . "\nClasse: " . $report['course_name'] . ' / ' . $report['class_name']
                    . "\nTitulo: " . $report['title']
                    . "\nAutor: " . $report['author_name']
                    . "\nTexto: " . $report['body'],
                $reports
            ));

        return "Aluno: {$student['name']}\n\nClasses:\n{$classText}\n\nRelatorios pedagogicos:\n{$reportText}\n\nGere um parecer final com: Contexto, Sintese dos registros, Potencialidades observadas, Pontos de atencao, Recomendações pedagogicas/pastorais e Parecer final.";
    }

    private function extractText(array $response): string
    {
        if (isset($response['output_text'])) {
            return trim((string) $response['output_text']);
        }

        $parts = [];

        foreach (($response['output'] ?? []) as $output) {
            foreach (($output['content'] ?? []) as $content) {
                if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) {
                    $parts[] = $content['text'];
                }
            }
        }

        return trim(implode("\n", $parts));
    }
}
