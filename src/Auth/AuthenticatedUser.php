<?php

declare(strict_types=1);

namespace App\Auth;

final class AuthenticatedUser
{
    public const ROLE_SUPER_ADMIN          = 'super_admin';
    public const ROLE_ESTABLISHMENT_ADMIN  = 'establishment_admin';
    public const ROLE_ESTABLISHMENT_USER   = 'establishment_user';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ESTABLISHMENT_ADMIN,
        self::ROLE_ESTABLISHMENT_USER,
    ];

    public function __construct(
        public readonly int $id,
        public readonly string $role,
        public readonly ?int $establishmentId,
        public readonly string $email,
        public readonly string $name,
    ) {
        if (!in_array($role, self::ROLES, true)) {
            throw new \InvalidArgumentException("Rol inválido: {$role}");
        }
        if ($role === self::ROLE_SUPER_ADMIN && $establishmentId !== null) {
            throw new \InvalidArgumentException('super_admin no puede tener establishment_id.');
        }
        if ($role !== self::ROLE_SUPER_ADMIN && $establishmentId === null) {
            throw new \InvalidArgumentException("El rol {$role} requiere establishment_id.");
        }
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isEstablishmentAdmin(): bool
    {
        return $this->role === self::ROLE_ESTABLISHMENT_ADMIN;
    }

    public function isEstablishmentUser(): bool
    {
        return $this->role === self::ROLE_ESTABLISHMENT_USER;
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'role'             => $this->role,
            'establishment_id' => $this->establishmentId,
            'email'            => $this->email,
            'name'             => $this->name,
        ];
    }
}
