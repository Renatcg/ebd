<?php

declare(strict_types=1);

final class Config
{
    public static function databaseUrl(): ?string
    {
        $url = getenv('DATABASE_URL')
            ?: getenv('POSTGRES_URL_NON_POOLING')
            ?: getenv('POSTGRES_URL')
            ?: getenv('POSTGRES_PRISMA_URL')
            ?: '';

        $url = trim((string) $url);

        return $url === '' ? null : $url;
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
