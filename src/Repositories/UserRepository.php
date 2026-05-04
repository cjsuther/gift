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

    // -----------------------------------------------------------------------
    // Métodos globales para super_admin (sin tenant scope).
    // El controller debe aplicar las reglas de negocio (no auto-desactivar,
    // no tocar otros super_admins, etc.); el repo solo provee el acceso.
    // -----------------------------------------------------------------------

    /**
     * Lista TODOS los usuarios del sistema con filtros opcionales.
     * Joinea establishment para mostrar el nombre.
     *
     * @param array{q?:string,role?:string,establishment_id?:int|null,is_active?:int} $filters
     * @return array<int, array<string, mixed>>
     */
    public function listAll(array $filters = []): array
    {
        $sql = 'SELECT u.id, u.role, u.establishment_id, u.name, u.email,
                       u.is_active, u.last_login_at, u.created_at,
                       e.name AS establishment_name
                FROM users u
                LEFT JOIN establishments e ON e.id = u.establishment_id
                WHERE 1 = 1';
        $params = [];

        if (!empty($filters['q'])) {
            $sql            .= ' AND (u.name LIKE :q OR u.email LIKE :q)';
            $params['q']     = '%' . trim((string) $filters['q']) . '%';
        }
        if (!empty($filters['role'])) {
            $sql               .= ' AND u.role = :role';
            $params['role']     = (string) $filters['role'];
        }
        if (array_key_exists('establishment_id', $filters)) {
            if ($filters['establishment_id'] === null) {
                $sql .= ' AND u.establishment_id IS NULL';
            } else {
                $sql            .= ' AND u.establishment_id = :eid';
                $params['eid']   = (int) $filters['establishment_id'];
            }
        }
        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== '') {
            $sql                  .= ' AND u.is_active = :active';
            $params['active']      = (int) $filters['is_active'];
        }

        $sql .= ' ORDER BY u.is_active DESC,
                          FIELD(u.role, "super_admin", "establishment_admin", "establishment_user"),
                          u.name ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findAny(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.role, u.establishment_id, u.name, u.email,
                    u.is_active, u.last_login_at, u.created_at,
                    e.name AS establishment_name
             FROM users u
             LEFT JOIN establishments e ON e.id = u.establishment_id
             WHERE u.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Update sin tenant scope. Whitelist explícita de campos:
     * NO permite cambiar `role` ni `establishment_id` (esos serían cambios
     * de privilegio o de pertenencia con riesgos que requieren UI dedicada).
     */
    public function updateAny(int $id, array $data): void
    {
        $allowed = ['name', 'email', 'is_active', 'password'];
        $sets    = [];
        $params  = ['id' => $id];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            if ($field === 'password') {
                if ($data['password'] === null || $data['password'] === '') {
                    continue;
                }
                $sets[]            = 'password_hash = :hash';
                $params['hash']    = password_hash((string) $data['password'], PASSWORD_BCRYPT);
                continue;
            }
            if ($field === 'is_active') {
                $sets[]               = 'is_active = :is_active';
                $params['is_active']  = (int) (bool) $data['is_active'];
                continue;
            }
            $sets[]            = "{$field} = :{$field}";
            $params[$field]    = $data[$field];
        }

        if ($sets === []) {
            return;
        }

        $sql  = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function deactivateAny(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET is_active = 0 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Trae solo el password_hash del usuario para verificación de credenciales
     * (cambio de password en el perfil propio). NO usar para enriquecer la
     * vista — los demás métodos no exponen password_hash a propósito.
     */
    public function getPasswordHash(int $id): ?string
    {
        $stmt = $this->pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        return (string) $row['password_hash'];
    }
}
