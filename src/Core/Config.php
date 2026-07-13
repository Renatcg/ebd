<?php

declare(strict_types=1);

final class Config
{
    public static function databaseUrl(): ?string
    {
        $provider = strtolower(trim((string) getenv('EBD_DATABASE_PROVIDER')));
        $url = $provider === 'neon'
            ? (self::neonDatabaseUrl() ?: self::defaultPostgresUrl())
            : (self::defaultPostgresUrl() ?: self::neonDatabaseUrl());

        $url = trim((string) $url);

        return $url === '' ? null : $url;
    }

    private static function neonDatabaseUrl(): string
    {
        return getenv('NEON_EBD_URL')
            ?: getenv('NEON_EBD_DATABASE_URL')
            ?: getenv('NEON_EBD_POSTGRES_URL')
            ?: getenv('NEON_EBD_POSTGRES_PRISMA_URL')
            ?: getenv('NEON_EBD_POSTGRES_URL_NON_POOLING')
            ?: self::postgresUrlFromParts('NEON_EBD_')
            ?: '';
    }

    private static function defaultPostgresUrl(): string
    {
        return getenv('DATABASE_URL')
            ?: getenv('POSTGRES_URL_NON_POOLING')
            ?: getenv('POSTGRES_URL')
            ?: getenv('POSTGRES_PRISMA_URL')
            ?: self::postgresUrlFromParts('')
            ?: '';
    }

    private static function postgresUrlFromParts(string $prefix): string
    {
        $host = trim((string) getenv($prefix . 'POSTGRES_HOST'));
        $database = trim((string) getenv($prefix . 'POSTGRES_DATABASE'));
        $user = trim((string) getenv($prefix . 'POSTGRES_USER'));
        $password = (string) getenv($prefix . 'POSTGRES_PASSWORD');

        if ($host === '' || $database === '' || $user === '') {
            return '';
        }

        return sprintf(
            'postgresql://%s:%s@%s/%s?sslmode=require',
            rawurlencode($user),
            rawurlencode($password),
            $host,
            rawurlencode($database)
        );
    }

    public static function databasePath(): string
    {
        if (getenv('EBD_DATABASE_PATH')) {
            return (string) getenv('EBD_DATABASE_PATH');
        }

        if (getenv('VERCEL')) {
            return '/tmp/ebd.sqlite';
        }

        return dirname(__DIR__, 2) . '/data/ebd.sqlite';
    }

    public static function jwtSecret(): string
    {
        return getenv('EBD_JWT_SECRET') ?: 'troque-este-segredo-em-producao';
    }

    public static function jwtTtlSeconds(): int
    {
        return (int) (getenv('EBD_JWT_TTL_SECONDS') ?: 86400);
    }
}
