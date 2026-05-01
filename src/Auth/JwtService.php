<?php

declare(strict_types=1);

namespace App\Auth;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService
{
    public function __construct(
        private readonly string $secret,
        private readonly int $ttlHours = 2,
        private readonly string $issuer = 'giftcards-app',
        private readonly string $algo = 'HS256',
    ) {
        if (strlen($this->secret) < 16) {
            throw new \InvalidArgumentException('JWT_SECRET demasiado corto (mínimo 16 caracteres).');
        }
    }

    public function issue(int $userId, string $role, ?int $establishmentId): string
    {
        $now = time();
        $payload = [
            'iss'    => $this->issuer,
            'sub'    => $userId,
            'iat'    => $now,
            'exp'    => $now + ($this->ttlHours * 3600),
            'role'   => $role,
            'tenant' => $establishmentId,
        ];

        return JWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * @return array{sub:int, role:string, tenant:int|null, exp:int, iat:int, iss:string}
     * @throws \UnexpectedValueException si el token es inválido o expiró
     */
    public function decode(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->secret, $this->algo));
        $payload = (array) $decoded;

        if (!isset($payload['sub'], $payload['role'])) {
            throw new \UnexpectedValueException('JWT inválido: faltan claims requeridos.');
        }

        $payload['sub']    = (int) $payload['sub'];
        $payload['tenant'] = isset($payload['tenant']) ? (int) $payload['tenant'] : null;

        return $payload;
    }
}
