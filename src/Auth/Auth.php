<?php

declare(strict_types=1);

final class Auth
{
    public static function attempt(string $email, string $password): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return null;
        }

        return self::publicUser($user);
    }

    public static function tokenFor(array $user): string
    {
        return Jwt::encode([
            'sub' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + Config::jwtTtlSeconds(),
        ]);
    }

    public static function user(): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/Bearer\s+(.+)/', $header, $matches)) {
            return null;
        }

        $payload = Jwt::decode($matches[1]);

        if ($payload === null || !isset($payload['sub'])) {
            return null;
        }

        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $payload['sub']]);
        $user = $stmt->fetch();

        return $user ? self::publicUser($user) : null;
    }

    public static function requireUser(): array
    {
        $user = self::user();

        if ($user === null) {
            Response::error('Autenticacao obrigatoria.', 401);
        }

        return $user;
    }

    public static function requireRole(array $roles): array
    {
        $user = self::requireUser();

        if (!in_array($user['role'], $roles, true)) {
            Response::error('Acesso nao permitido.', 403);
        }

        return $user;
    }

    private static function publicUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'person_id' => isset($user['person_id']) ? (int) $user['person_id'] : null,
        ];
    }
}
