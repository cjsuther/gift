<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthenticatedUser;
use App\Helpers\Response as ApiResponse;
use App\Helpers\Validator;
use App\Middleware\AuthMiddleware;
use App\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Gestión global de usuarios para super_admin.
 *
 * Reglas de negocio (defensivas, además del whitelist del repo):
 *  - super_admin NO puede desactivarse a sí mismo.
 *  - super_admin NO puede editar/desactivar a OTROS super_admins.
 *    Para tocar otro super_admin hay que ir por SQL/seed (intencional, evita
 *    pelea entre super admins). Su propio registro sí puede editarlo.
 *  - El rol y el establishment_id son inmutables por esta UI: si vienen en el
 *    body, se ignoran (el repo ya hace whitelist; redundamos acá por claridad).
 */
final class AdminUserController
{
    public function __construct(private readonly UserRepository $repo)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $q = $request->getQueryParams();

        $filters = [];
        if (!empty($q['q']))    { $filters['q']    = (string) $q['q']; }
        if (!empty($q['role'])) { $filters['role'] = (string) $q['role']; }
        if (isset($q['establishment_id']) && $q['establishment_id'] !== '') {
            $filters['establishment_id'] = (int) $q['establishment_id'];
        }
        if (isset($q['is_active']) && $q['is_active'] !== '') {
            $filters['is_active'] = (int) $q['is_active'];
        }

        return ApiResponse::json($response, [
            'users' => $this->repo->listAll($filters),
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $row = $this->repo->findAny((int) ($args['id'] ?? 0));
        if ($row === null) {
            return ApiResponse::notFound($response, 'Usuario no encontrado.');
        }
        return ApiResponse::json($response, ['user' => $row]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $current = $this->currentUser($request);
        $id      = (int) ($args['id'] ?? 0);
        $row     = $this->repo->findAny($id);
        if ($row === null) {
            return ApiResponse::notFound($response, 'Usuario no encontrado.');
        }

        if ($this->isOtherSuperAdmin($row, $current)) {
            return ApiResponse::forbidden($response, 'No podés editar a otro super admin.');
        }

        $data = $this->parseBody($request);

        $v = new Validator($data);
        if (array_key_exists('name', $data))  { $v->minLength('name', 2); }
        if (array_key_exists('email', $data)) { $v->email('email'); }
        if (array_key_exists('password', $data) && $data['password'] !== '') {
            $v->minLength('password', 8);
        }
        if ($v->fails()) {
            return ApiResponse::error($response, 'Datos inválidos.', 422, ['fields' => $v->errors()]);
        }

        if (array_key_exists('email', $data) && $data['email'] !== $row['email']) {
            if ($this->repo->emailExists((string) $data['email'], $id)) {
                return ApiResponse::error($response, 'Ya existe un usuario con ese email.', 422, [
                    'fields' => ['email' => 'Email ya registrado.'],
                ]);
            }
        }

        $patch = [];
        foreach (['name', 'email'] as $field) {
            if (array_key_exists($field, $data)) {
                $patch[$field] = trim((string) $data[$field]);
            }
        }
        if (array_key_exists('password', $data) && $data['password'] !== '') {
            $patch['password'] = (string) $data['password'];
        }
        if (array_key_exists('is_active', $data)) {
            $newActive = (int) (bool) $data['is_active'];
            if ($newActive === 0 && $current->id === $id) {
                return ApiResponse::error(
                    $response,
                    'No podés desactivarte a vos mismo.',
                    422,
                    ['fields' => ['is_active' => 'Auto-desactivación bloqueada.']]
                );
            }
            $patch['is_active'] = $newActive;
        }

        $this->repo->updateAny($id, $patch);

        return ApiResponse::json($response, ['user' => $this->repo->findAny($id)]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $current = $this->currentUser($request);
        $id      = (int) ($args['id'] ?? 0);
        $row     = $this->repo->findAny($id);
        if ($row === null) {
            return ApiResponse::notFound($response, 'Usuario no encontrado.');
        }
        if ($current->id === $id) {
            return ApiResponse::error($response, 'No podés desactivarte a vos mismo.', 422);
        }
        if ($this->isOtherSuperAdmin($row, $current)) {
            return ApiResponse::forbidden($response, 'No podés desactivar a otro super admin.');
        }

        $this->repo->deactivateAny($id);
        return ApiResponse::json($response, ['ok' => true]);
    }

    private function isOtherSuperAdmin(array $row, AuthenticatedUser $current): bool
    {
        return ($row['role'] ?? null) === AuthenticatedUser::ROLE_SUPER_ADMIN
            && (int) $row['id'] !== $current->id;
    }

    private function currentUser(ServerRequestInterface $request): AuthenticatedUser
    {
        $u = $request->getAttribute(AuthMiddleware::REQUEST_ATTR);
        if (!$u instanceof AuthenticatedUser) {
            throw new \RuntimeException('Usuario no inyectado por AuthMiddleware.');
        }
        return $u;
    }

    private function parseBody(ServerRequestInterface $request): array
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed)) {
            return $parsed;
        }
        $raw = (string) $request->getBody();
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
