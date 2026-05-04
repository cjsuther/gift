<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class GiftcardRepository
{
    public const PER_PAGE_DEFAULT = 20;
    public const PER_PAGE_MAX     = 100;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function tokenExists(string $token): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM giftcards WHERE token = :t LIMIT 1');
        $stmt->execute(['t' => $token]);
        return $stmt->fetch() !== false;
    }

    /**
     * Lista paginada y filtrada por tenant.
     *
     * @param array{q?:string,status?:string,sort?:string,page?:int,per_page?:int} $filters
     * @return array{items:array<int,array<string,mixed>>, total:int, page:int, per_page:int, total_pages:int, counts:array<string,int>}
     */
    public function listForTenant(int $establishmentId, array $filters = []): array
    {
        $perPage = (int) ($filters['per_page'] ?? self::PER_PAGE_DEFAULT);
        if ($perPage < 1)                       $perPage = self::PER_PAGE_DEFAULT;
        if ($perPage > self::PER_PAGE_MAX)      $perPage = self::PER_PAGE_MAX;

        $page = max(1, (int) ($filters['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        [$where, $params] = $this->buildWhere($establishmentId, $filters);
        $params['limit']  = $perPage;
        $params['offset'] = $offset;

        $sort = $this->resolveSort($filters['sort'] ?? null);

        $sql = "SELECT g.id, g.token, g.title, g.description, g.image_path, g.status,
                       g.expires_at, g.recipient_name, g.recipient_contact,
                       g.created_at, g.updated_at, g.redeemed_at,
                       g.created_by_user_id, g.redeemed_by_user_id,
                       cu.name AS created_by_name,
                       ru.name AS redeemed_by_name
                FROM giftcards g
                LEFT JOIN users cu ON cu.id = g.created_by_user_id
                LEFT JOIN users ru ON ru.id = g.redeemed_by_user_id
                {$where}
                ORDER BY {$sort}
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $type = ($k === 'limit' || $k === 'offset' || $k === 'eid') ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($k, $v, $type);
        }
        $stmt->execute();
        $items = $stmt->fetchAll();

        // Total para paginación (mismo WHERE, sin LIMIT)
        $countSql = "SELECT COUNT(*) FROM giftcards g {$where}";
        $countStmt = $this->pdo->prepare($countSql);
        $countParams = $params;
        unset($countParams['limit'], $countParams['offset']);
        $countStmt->execute($countParams);
        $total = (int) $countStmt->fetchColumn();

        return [
            'items'        => $items,
            'total'        => $total,
            'page'         => $page,
            'per_page'     => $perPage,
            'total_pages'  => max(1, (int) ceil($total / $perPage)),
            'counts'       => $this->countsByStatus($establishmentId),
        ];
    }

    public function countsByStatus(int $establishmentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT status, COUNT(*) AS n
             FROM giftcards
             WHERE establishment_id = :eid
             GROUP BY status'
        );
        $stmt->execute(['eid' => $establishmentId]);

        $base = ['active' => 0, 'redeemed' => 0, 'expired' => 0, 'cancelled' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $base[(string) $row['status']] = (int) $row['n'];
        }
        $base['total'] = array_sum($base);
        return $base;
    }

    public function findForTenant(int $id, int $establishmentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT g.*, cu.name AS created_by_name, ru.name AS redeemed_by_name
             FROM giftcards g
             LEFT JOIN users cu ON cu.id = g.created_by_user_id
             LEFT JOIN users ru ON ru.id = g.redeemed_by_user_id
             WHERE g.id = :id AND g.establishment_id = :eid LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'eid' => $establishmentId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Crea giftcard. El image_path se setea con un closure post-INSERT
     * (necesitamos el id+token primero para armar el path final del archivo).
     *
     * @param callable|null $imageSaver fn(string $token):string|null devuelve el path relativo
     */
    public function create(int $establishmentId, int $createdByUserId, string $token, array $data, ?callable $imageSaver = null): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO giftcards
             (establishment_id, created_by_user_id, token, title, description,
              status, expires_at, recipient_name, recipient_contact)
             VALUES
             (:eid, :uid, :token, :title, :description,
              'active', :expires_at, :rname, :rcontact)"
        );
        $stmt->execute([
            'eid'         => $establishmentId,
            'uid'         => $createdByUserId,
            'token'       => $token,
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'expires_at'  => $data['expires_at'] ?? null,
            'rname'       => $data['recipient_name'] ?? null,
            'rcontact'    => $data['recipient_contact'] ?? null,
        ]);
        $id = (int) $this->pdo->lastInsertId();

        if ($imageSaver !== null) {
            $imagePath = $imageSaver($token);
            if (is_string($imagePath) && $imagePath !== '') {
                $upd = $this->pdo->prepare('UPDATE giftcards SET image_path = :p WHERE id = :id');
                $upd->execute(['p' => $imagePath, 'id' => $id]);
            }
        }

        $this->log($id, $createdByUserId, 'created');
        return $id;
    }

    /**
     * Actualiza giftcard. Solo si pertenece al tenant Y status = active.
     * Devuelve true si modificó algo.
     */
    public function updateActive(int $id, int $establishmentId, int $editedByUserId, array $data, ?callable $imageSaver = null): bool
    {
        $existing = $this->findForTenant($id, $establishmentId);
        if ($existing === null || $existing['status'] !== 'active') {
            return false;
        }

        $sets   = [];
        $params = ['id' => $id, 'eid' => $establishmentId];

        foreach (['title', 'description', 'expires_at', 'recipient_name', 'recipient_contact'] as $field) {
            if (array_key_exists($field, $data)) {
                $sets[]            = "{$field} = :{$field}";
                $params[$field]    = $data[$field];
            }
        }

        if ($imageSaver !== null) {
            $imagePath = $imageSaver((string) $existing['token']);
            if (is_string($imagePath) && $imagePath !== '') {
                $sets[]                  = 'image_path = :image_path';
                $params['image_path']    = $imagePath;
            }
        }

        if ($sets === []) {
            return false;
        }

        $sql  = 'UPDATE giftcards SET ' . implode(', ', $sets)
              . " WHERE id = :id AND establishment_id = :eid AND status = 'active'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $this->log($id, $editedByUserId, 'edited');
        return true;
    }

    /**
     * Cancela una giftcard activa. Devuelve true si la canceló.
     */
    public function cancel(int $id, int $establishmentId, int $byUserId): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE giftcards SET status = 'cancelled'
             WHERE id = :id AND establishment_id = :eid AND status = 'active'"
        );
        $stmt->execute(['id' => $id, 'eid' => $establishmentId]);
        if ($stmt->rowCount() === 0) {
            return false;
        }
        $this->log($id, $byUserId, 'cancelled');
        return true;
    }

    /**
     * Lookup por token completo de 32 hex, scoped por tenant.
     * Devuelve null si el token no existe O no pertenece al establecimiento del usuario.
     * Esa colapso es intencional: NO leakeamos si un token existe en otro establecimiento.
     */
    public function findByTokenForTenant(string $token, int $establishmentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT g.*, cu.name AS created_by_name, ru.name AS redeemed_by_name
             FROM giftcards g
             LEFT JOIN users cu ON cu.id = g.created_by_user_id
             LEFT JOIN users ru ON ru.id = g.redeemed_by_user_id
             WHERE g.token = :t AND g.establishment_id = :eid LIMIT 1'
        );
        $stmt->execute(['t' => $token, 'eid' => $establishmentId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Lookup PÚBLICO por token, SIN tenant scope. Incluye datos del establecimiento
     * para que la vista pública pueda mostrar branding (nombre, color primario, logo).
     *
     * Trade-off de seguridad: anyone con la URL del QR puede ver imagen/título/desc.
     * Es lo esperado: el QR está diseñado para ser presentado al cliente. El token
     * es el secreto (32 hex random). Lo que sigue protegido es el ACTO de canjear.
     */
    public function findByTokenAny(string $token): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT g.*, cu.name AS created_by_name, ru.name AS redeemed_by_name,
                    e.name AS establishment_name, e.address AS establishment_address,
                    e.phone AS establishment_phone, e.logo_path AS establishment_logo_path,
                    e.primary_color AS establishment_primary_color
             FROM giftcards g
             LEFT JOIN users cu ON cu.id = g.created_by_user_id
             LEFT JOIN users ru ON ru.id = g.redeemed_by_user_id
             LEFT JOIN establishments e ON e.id = g.establishment_id
             WHERE g.token = :t LIMIT 1'
        );
        $stmt->execute(['t' => $token]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Lookup por short token (8 chars). Si hay 1 sola coincidencia → devuelve el row.
     * Si hay 0 → null. Si hay >1 → array vacío []. El controller distingue.
     *
     * @return array<string,mixed>|null|array{}
     */
    public function findByShortTokenForTenant(string $shortToken, int $establishmentId): array|null
    {
        $shortToken = strtolower(trim($shortToken));
        if (preg_match('/^[a-f0-9]{4,32}$/', $shortToken) !== 1) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT id FROM giftcards
             WHERE establishment_id = :eid AND token LIKE :prefix
             LIMIT 5'
        );
        $stmt->execute([
            'eid'    => $establishmentId,
            'prefix' => $shortToken . '%',
        ]);
        $rows = $stmt->fetchAll();
        if (count($rows) === 0) {
            return null;
        }
        if (count($rows) > 1) {
            return [];   // ambigüedad — el controller decide qué hacer
        }
        return $this->findForTenant((int) $rows[0]['id'], $establishmentId);
    }

    /**
     * Marca como canjeada en una sola transacción atómica:
     * - Solo modifica si status=active y (expires_at NULL o >= hoy).
     * - Devuelve true si quedó canjeada en este intento.
     * - Si otro request la canjeó al mismo tiempo, este devuelve false (rowCount=0).
     */
    public function redeem(int $id, int $establishmentId, int $byUserId, ?string $ip, ?string $ua): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE giftcards
             SET status = 'redeemed', redeemed_at = NOW(), redeemed_by_user_id = :uid
             WHERE id = :id
               AND establishment_id = :eid
               AND status = 'active'
               AND (expires_at IS NULL OR expires_at >= CURDATE())"
        );
        $stmt->execute([
            'id'  => $id,
            'eid' => $establishmentId,
            'uid' => $byUserId,
        ]);
        if ($stmt->rowCount() === 0) {
            return false;
        }
        $this->log($id, $byUserId, 'redeemed', null, $ip, $ua);
        return true;
    }

    /**
     * KPIs del establecimiento para el dashboard.
     *
     * @return array{
     *   counts: array<string,int>,
     *   redeemed_this_month: int,
     *   created_this_month: int,
     *   redemption_rate: float
     * }
     */
    public function statsForTenant(int $establishmentId): array
    {
        $counts = $this->countsByStatus($establishmentId);

        // Nota: PDO con EMULATE_PREPARES=false no permite reusar el mismo
        // placeholder named, por eso usamos :fom1 y :fom2.
        $stmt = $this->pdo->prepare(
            "SELECT
                SUM(status = 'redeemed' AND redeemed_at >= :fom1)  AS redeemed_this_month,
                SUM(created_at >= :fom2)                            AS created_this_month
             FROM giftcards
             WHERE establishment_id = :eid"
        );
        $firstOfMonth = date('Y-m-01 00:00:00');
        $stmt->execute([
            'eid'  => $establishmentId,
            'fom1' => $firstOfMonth,
            'fom2' => $firstOfMonth,
        ]);
        $row = $stmt->fetch();

        $totalIssued     = (int) $counts['total'];
        $totalRedeemed   = (int) $counts['redeemed'];
        $redemptionRate  = $totalIssued > 0 ? round(($totalRedeemed / $totalIssued) * 100, 1) : 0.0;

        return [
            'counts'              => $counts,
            'redeemed_this_month' => (int) ($row['redeemed_this_month'] ?? 0),
            'created_this_month'  => (int) ($row['created_this_month'] ?? 0),
            'redemption_rate'     => $redemptionRate,
        ];
    }

    /**
     * Últimas N giftcards creadas para mostrar en el dashboard.
     * @return array<int,array<string,mixed>>
     */
    public function recentCreatedForTenant(int $establishmentId, int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT g.id, g.token, g.title, g.image_path, g.status, g.created_at,
                    g.recipient_name
             FROM giftcards g
             WHERE g.establishment_id = :eid
             ORDER BY g.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('eid', $establishmentId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Últimas N canjeadas para mostrar en el dashboard.
     * @return array<int,array<string,mixed>>
     */
    public function recentRedeemedForTenant(int $establishmentId, int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT g.id, g.token, g.title, g.image_path, g.redeemed_at, g.recipient_name,
                    ru.name AS redeemed_by_name
             FROM giftcards g
             LEFT JOIN users ru ON ru.id = g.redeemed_by_user_id
             WHERE g.establishment_id = :eid AND g.status = 'redeemed'
             ORDER BY g.redeemed_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue('eid', $establishmentId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function log(int $giftcardId, ?int $userId, string $action, ?string $notes = null, ?string $ip = null, ?string $ua = null): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO giftcard_logs (giftcard_id, user_id, action, ip_address, user_agent, notes)
             VALUES (:gid, :uid, :action, :ip, :ua, :notes)'
        );
        $stmt->execute([
            'gid'    => $giftcardId,
            'uid'    => $userId,
            'action' => $action,
            'ip'     => $ip,
            'ua'     => $ua !== null ? substr($ua, 0, 255) : null,
            'notes'  => $notes,
        ]);
    }

    // ---------- internos ----------

    /**
     * @return array{0:string, 1:array<string,mixed>}
     */
    private function buildWhere(int $establishmentId, array $filters): array
    {
        $where  = ['g.establishment_id = :eid'];
        $params = ['eid' => $establishmentId];

        $status = $filters['status'] ?? null;
        if (in_array($status, ['active', 'redeemed', 'expired', 'cancelled'], true)) {
            $where[]            = 'g.status = :status';
            $params['status']   = $status;
        }

        if (!empty($filters['q'])) {
            $where[]            = '(g.title LIKE :q OR g.recipient_name LIKE :q OR g.recipient_contact LIKE :q)';
            $params['q']        = '%' . trim((string) $filters['q']) . '%';
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    private function resolveSort(?string $sort): string
    {
        return match ($sort) {
            'redeemed_at' => 'g.redeemed_at DESC',
            'created_asc' => 'g.created_at ASC',
            default       => 'g.created_at DESC',
        };
    }
}
