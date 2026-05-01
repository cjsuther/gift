<?php

declare(strict_types=1);

namespace App\Auth;

use PDO;

final class PdoUserRepository implements UserProvider
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findActiveById(int $id): ?AuthenticatedUser
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, role, establishment_id, email, name, is_active
             FROM users
             WHERE id = :id AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findActiveByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, role, establishment_id, email, name, password_hash, is_active
             FROM users
             WHERE email = :email AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function touchLastLogin(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function hydrate(array $row): AuthenticatedUser
    {
        return new AuthenticatedUser(
            id:              (int) $row['id'],
            role:            (string) $row['role'],
            establishmentId: $row['establishment_id'] !== null ? (int) $row['establishment_id'] : null,
            email:           (string) $row['email'],
            name:            (string) $row['name'],
        );
    }
}
