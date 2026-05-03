<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class EstablishmentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(?string $search = null): array
    {
        $sql = 'SELECT id, name, slug, address, phone, email, logo_path, primary_color,
                       is_active, created_at, updated_at
                FROM establishments';
        $params = [];

        if ($search !== null && trim($search) !== '') {
            $sql      .= ' WHERE name LIKE :q OR slug LIKE :q';
            $params['q'] = '%' . trim($search) . '%';
        }

        $sql .= ' ORDER BY is_active DESC, name ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, slug, address, phone, email, logo_path, primary_color,
                    is_active, created_at, updated_at
             FROM establishments WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql    = 'SELECT 1 FROM establishments WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($exceptId !== null) {
            $sql            .= ' AND id <> :id';
            $params['id']    = $exceptId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() !== false;
    }

    /**
     * Crea establishment + su primer establishment_admin en una transacción.
     * Si pasás $logoSaver, se invoca con el id recién creado para guardar el archivo
     * y devolver el path relativo (queda persistido en logo_path).
     *
     * @return array{establishment_id:int, admin_user_id:int}
     */
    public function createWithAdmin(array $data, array $admin, ?\Closure $logoSaver = null): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO establishments (name, slug, address, phone, email, primary_color, is_active)
                 VALUES (:name, :slug, :address, :phone, :email, :primary_color, 1)'
            );
            $stmt->execute([
                'name'          => $data['name'],
                'slug'          => $data['slug'],
                'address'       => $data['address'] ?? null,
                'phone'         => $data['phone'] ?? null,
                'email'         => $data['email'] ?? null,
                'primary_color' => $data['primary_color'] ?? '#111827',
            ]);
            $estId = (int) $this->pdo->lastInsertId();

            if ($logoSaver !== null) {
                $logoPath = $logoSaver($estId);
                if (is_string($logoPath) && $logoPath !== '') {
                    $upd = $this->pdo->prepare('UPDATE establishments SET logo_path = :p WHERE id = :id');
                    $upd->execute(['p' => $logoPath, 'id' => $estId]);
                }
            }

            $stmt = $this->pdo->prepare(
                "INSERT INTO users (establishment_id, role, name, email, password_hash, is_active)
                 VALUES (:eid, 'establishment_admin', :name, :email, :hash, 1)"
            );
            $stmt->execute([
                'eid'   => $estId,
                'name'  => $admin['name'],
                'email' => $admin['email'],
                'hash'  => password_hash((string) $admin['password'], PASSWORD_BCRYPT),
            ]);
            $userId = (int) $this->pdo->lastInsertId();

            $this->pdo->commit();
            return ['establishment_id' => $estId, 'admin_user_id' => $userId];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function update(int $id, array $data, ?\Closure $logoSaver = null): void
    {
        $sets   = [];
        $params = ['id' => $id];

        foreach (['name', 'slug', 'address', 'phone', 'email', 'primary_color'] as $field) {
            if (array_key_exists($field, $data)) {
                $sets[]            = "{$field} = :{$field}";
                $params[$field]    = $data[$field];
            }
        }

        if (array_key_exists('is_active', $data)) {
            $sets[]               = 'is_active = :is_active';
            $params['is_active']  = (int) (bool) $data['is_active'];
        }

        if ($logoSaver !== null) {
            $logoPath = $logoSaver($id);
            if (is_string($logoPath) && $logoPath !== '') {
                $sets[]                 = 'logo_path = :logo_path';
                $params['logo_path']    = $logoPath;
            }
        }

        if ($sets === []) {
            return;
        }

        $sql  = 'UPDATE establishments SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function deactivate(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE establishments SET is_active = 0 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
