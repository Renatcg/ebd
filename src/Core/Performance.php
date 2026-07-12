<?php

declare(strict_types=1);

final class Performance
{
    private static float $startedAt = 0.0;
    /** @var array<string, float> */
    private static array $timings = [];

    public static function start(): void
    {
        if (self::$startedAt === 0.0) {
            self::$startedAt = microtime(true);
        }
    }

    public static function measure(string $name, callable $callback): mixed
    {
        self::start();
        $startedAt = microtime(true);

        try {
            return $callback();
        } finally {
            self::add($name, (microtime(true) - $startedAt) * 1000);
        }
    }

    public static function add(string $name, float $milliseconds): void
    {
        self::$timings[$name] = (self::$timings[$name] ?? 0.0) + $milliseconds;
    }

    public static function totalMilliseconds(): float
    {
        self::start();

        return (microtime(true) - self::$startedAt) * 1000;
    }

    public static function logResponse(int $status, int $payloadBytes): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        if (!str_starts_with($path, '/api')) {
            return;
        }

        $total = self::totalMilliseconds();
        $timings = [];

        foreach (self::$timings as $name => $milliseconds) {
            $timings[$name] = round($milliseconds, 1);
        }

        error_log('[ebd-perf] ' . json_encode([
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'path' => $path,
            'status' => $status,
            'total_ms' => round($total, 1),
            'payload_bytes' => $payloadBytes,
            'timings' => $timings,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
