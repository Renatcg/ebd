<?php

declare(strict_types=1);

final class Response
{
    public static function json(array $payload, int $status = 200): void
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $body = $body === false ? '{}' : $body;

        if (class_exists('Performance')) {
            Performance::logResponse($status, strlen($body));
            header('X-EBD-Time-Ms: ' . round(Performance::totalMilliseconds(), 1));
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo $body;
        exit;
    }

    public static function error(string $message, int $status): void
    {
        self::json(['error' => $message], $status);
    }
}
