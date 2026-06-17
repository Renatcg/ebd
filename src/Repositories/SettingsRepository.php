<?php

declare(strict_types=1);

final class SettingsRepository
{
    public function get(string $key, ?string $fallback = null): ?string
    {
        $stmt = Database::connection()->prepare('SELECT value FROM app_settings WHERE key = :key');
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();

        return $value === false ? $fallback : (string) $value;
    }

    public function set(string $key, ?string $value): void
    {
        $connection = Database::connection();
        $update = $connection->prepare(
            'UPDATE app_settings
             SET value = :value, updated_at = CURRENT_TIMESTAMP
             WHERE key = :key'
        );
        $update->execute([
            'key' => $key,
            'value' => $value,
        ]);

        if ($update->rowCount() > 0) {
            return;
        }

        $insert = $connection->prepare(
            'INSERT INTO app_settings (key, value, updated_at)
             VALUES (:key, :value, CURRENT_TIMESTAMP)'
        );
        $insert->execute([
            'key' => $key,
            'value' => $value,
        ]);
    }

    public function publicSettings(): array
    {
        $apiKey = $this->get('openai_api_key');

        return [
            'openai_configured' => $apiKey !== null && trim($apiKey) !== '',
            'openai_model' => $this->get('openai_model', 'gpt-5.5'),
            'openai_opinion_prompt' => $this->get('openai_opinion_prompt', $this->defaultOpinionPrompt()),
        ];
    }

    public function defaultOpinionPrompt(): string
    {
        return 'Voce e um coordenador pedagogico de uma Escola Biblica Dominical. Gere um parecer claro, respeitoso, pastoral e objetivo, baseado apenas nos relatorios fornecidos. Nao invente fatos. Quando houver poucos registros, diga isso com cuidado. Escreva em portugues do Brasil.';
    }
}
