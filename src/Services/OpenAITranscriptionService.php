<?php

declare(strict_types=1);

final class OpenAITranscriptionService
{
    public function transcribe(array $file): string
    {
        $stored = (new SettingsRepository())->get('openai_api_key');
        $apiKey = trim((string) ($stored ?: getenv('OPENAI_API_KEY') ?: ''));

        if ($apiKey === '') {
            Response::error('Configure a chave da OpenAI em Configuracoes.', 500);
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::error('Nao foi possivel receber o audio.', 422);
        }

        $path = (string) $file['tmp_name'];
        $name = (string) ($file['name'] ?? 'audio.webm');
        $type = (string) ($file['type'] ?? 'audio/webm');

        $curl = curl_init('https://api.openai.com/v1/audio/transcriptions');

        if ($curl === false) {
            Response::error('A extensao curl do PHP nao iniciou corretamente.', 500);
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => [
                'model' => 'gpt-4o-transcribe',
                'response_format' => 'json',
                'file' => new CURLFile($path, $type, $name),
            ],
        ]);

        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        if ($raw === false || $status < 200 || $status >= 300) {
            $decodedError = json_decode((string) $raw, true);
            $message = $decodedError['error']['message'] ?? null;
            Response::error($message ?: ($error !== '' ? $error : 'Falha ao transcrever audio.'), 502);
        }

        $decoded = json_decode((string) $raw, true);

        if (!is_array($decoded) || !isset($decoded['text'])) {
            Response::error('Resposta de transcricao inesperada.', 502);
        }

        return trim((string) $decoded['text']);
    }
}
