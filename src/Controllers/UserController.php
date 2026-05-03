<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response as ApiResponse;
use App\Helpers\Validator;
use App\Middleware\TenantMiddleware;
use App\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * CRUD de establishment_user, scoped al establecimiento del admin logueado.
 *
 * REGLA INVIOLABLE: el establishment_id se lee SIEMPRE del request attribute
 * inyectado por TenantMiddleware. Nunca del body, query o ruta. Esto bloquea
 * que un admin malicioso de un est. cree/modifique usuarios de otro est.
 */
final class UserController
{
    public function __construct(private readonly UserRepository $repo)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenant = $this->tenant($request);
        $q      = $request->getQueryParams()['q'] ?? null;
        return ApiResponse::json($response, [
            'users' => $this->repo->listForTenant($tenant, is_string($q) ? $q : null),
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenant = $this->tenant($request);
        $id     = (int) ($args['id'] ?? 0);
        $row    = $this->repo->findForTenant($id, $tenant);
        if ($row === null) {
            return ApiResponse::notFound($response, 'Usuario no encontrado.');
        }
        return ApiResponse::json($response, ['user' => $row]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenant = $this->tenant($request);
        $data   = $this->parseBody($request);

        $v = (new Validator($data))
            ->required('name')->minLength('name', 2)
            ->required('email')->email('email')
            ->required('password')->minLength('password', 8);

        if ($v->fails()) {
            return ApiResponse::error($response, 'Datos inválidos.', 422, ['fields' => $v->errors()]);
        }

        if ($this->repo->emailExists((string) $data['email'])) {
            return ApiResponse::error($response, 'Ya existe un usuario con ese email.', 422, [
                'fields' => ['email' => 'Email ya registrado.'],
            ]);
        }

        // OJO: ignoramos cualquier establishment_id o role que venga en el body.
        $id = $this->repo->create($tenant, [
            'name'     => trim((string) $data['name']),
            'email'    => trim((string) $data['email']),
            'password' => (string) $data['password'],
        ]);

        return ApiResponse::json($response, ['user' => $this->repo->findForTenant($id, $tenant)], 201);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenant = $this->tenant($request);
        $id     = (int) ($args['id'] ?? 0);
        $row    = $this->repo->findForTenant($id, $tenant);
        if ($row === null) {
            return ApiResponse::notFound($response, 'Usuario no encontrado.');
        }
        // Defensa adicional explícita además del WHERE en el repo.
        if (!TenantMiddleware::ownsResource($request, (int) $row['establishment_id'])) {
            return ApiResponse::forbidden($response);
        }

        $data = $this->parseBody($request);

        $v = new Validator($data);
        if (array_key_exists('name', $data))     { $v->minLength('name', 2); }
        if (array_key_exists('email', $data))    { $v->email('email'); }
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
            $patch['is_active'] = (int) (bool) $data['is_active'];
        }

        $this->repo->update($id, $tenant, $patch);

        return ApiResponse::json($response, ['user' => $this->repo->findForTenant($id, $tenant)]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenant = $this->tenant($request);
        $id     = (int) ($args['id'] ?? 0);
        $row    = $this->repo->findForTenant($id, $tenant);
        if ($row === null) {
            return ApiResponse::notFound($response, 'Usuario no encontrado.');
        }
        if (!TenantMiddleware::ownsResource($request, (int) $row['establishment_id'])) {
            return ApiResponse::forbidden($response);
        }

        $this->repo->deactivate($id, $tenant);
        return ApiResponse::json($response, ['ok' => true]);
    }

    private function tenant(ServerRequestInterface $request): int
    {
        $tenant = $request->getAttribute(TenantMiddleware::REQUEST_ATTR);
        if (!is_int($tenant)) {
            // No debería pasar: TenantMiddleware lo garantiza. Defensivo.
            throw new \RuntimeException('Tenant no inyectado en el request.');
        }
        return $tenant;
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
