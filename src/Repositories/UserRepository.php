<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Lista usuarios establishment_user de un establecimiento.
     * NO incluye al admin del establecimiento.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForTenant(int $establishmentId, ?string $search = null): array
    {
        $sql = "SELECT id, role, name, email, is_active, last_login_at, created_at
                FROM users
                WHERE establishment_id = :eid AND role = 'establishment_user'";
        $params = ['eid' => $establishmentId];

        if ($search !== null && trim($search) !== '') {
            $sql            .= ' AND (name LIKE :q OR email LIKE :q)';
            $params['q']     = '%' . trim($search) . '%';
        }

        $sql .= ' ORDER BY is_active DESC, name ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Busca un usuario por id pero solo si pertenece al tenant indicado y es establishment_user.
     * Esto evita que un admin de un est. lea/edite usuarios de otro establecimiento o admins.
     */
    public function findForTenant(int $id, int $establishmentId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, role, establishment_id, name, email, is_active, last_login_at, created_at
             FROM users
             WHERE id = :id AND establishment_id = :eid AND role = 'establishment_user'
             LIMIT 1"
        );
        $stmt->execute(['id' => $id, 'eid' => $establishmentId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql    = 'SELECT 1 FROM users WHERE email = :email';
        $params = ['email' => $email];

        if ($exceptId !== null) {
            $sql            .= ' AND id <> :id';
            $params['id']    = $exceptId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }

    /**
     * Crea un establishment_user. La SECURITY-CRITICAL aquí es que el caller
     * pase el $establishmentId desde TenantMiddleware (request attribute), NO
     * desde el body. El rol queda hardcoded como establishment_user.
     */
    public function create(int $establishmentId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO users (establishment_id, role, name, email, password_hash, is_active)
             VALUES (:eid, 'establishment_user', :name, :email, :hash, 1)"
        );
        $stmt->execute([
            'eid'   => $establishmentId,
            'name'  => $data['name'],
            'email' => $data['email'],
            'hash'  => password_hash((string) $data['password'], PASSWORD_BCRYPT),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update tenant-scoped: solo modifica si el usuario pertenece al establecimiento.
     * El UPDATE incluye el filtro por establishment_id como segunda capa de defensa
     * incluso si el caller olvida verificar pertenencia.
     */
    public function update(int $id, int $establishmentId, array $data): void
    {
        $sets   = [];
        $params = ['id' => $id, 'eid' => $establishmentId];

        if (array_key_exists('name', $data)) {
            $sets[]            = 'name = :name';
            $params['name']    = $data['name'];
        }
        if (array_key_exists('email', $data)) {
            $sets[]            = 'email = :email';
            $params['email']   = $data['email'];
        }
        if (array_key_exists('password', $data) && $data['password'] !== null && $data['password'] !== '') {
            $sets[]            = 'password_hash = :hash';
            $params['hash']    = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        }
        if (array_key_exists('is_active', $data)) {
            $sets[]               = 'is_active = :is_active';
            $params['is_active']  = (int) (bool) $data['is_active'];
        }

        if ($sets === []) {
            return;
        }

        $sql  = 'UPDATE users SET ' . implode(', ', $sets)
              . " WHERE id = :id AND establishment_id = :eid AND role = 'establishment_user'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function deactivate(int $id, int $establishmentId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE users SET is_active = 0
             WHERE id = :id AND establishment_id = :eid AND role = 'establishment_user'"
        );
        $stmt->execute(['id' => $id, 'eid' => $establishmentId]);
    }
}
