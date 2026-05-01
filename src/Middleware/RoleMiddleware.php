<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\AuthenticatedUser;
use App\Helpers\Response as ApiResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as SlimResponse;

/**
 * Restringe acceso a roles específicos. Se construye con la lista de
 * roles permitidos (whitelist explícita, no jerarquía). Debe colocarse
 * SIEMPRE después de AuthMiddleware en la cadena de la ruta.
 *
 * Ejemplos:
 *   new RoleMiddleware('super_admin')
 *   new RoleMiddleware('establishment_admin', 'establishment_user')
 *
 * Si el atributo `user` no está presente devuelve 401 (defensivo:
 * indica que el AuthMiddleware no corrió antes — bug de configuración).
 * Si el rol no está en la whitelist devuelve 403.
 */
final class RoleMiddleware implements MiddlewareInterface
{
    /** @var list<string> */
    private readonly array $allowedRoles;

    public function __construct(string ...$allowedRoles)
    {
        if ($allowedRoles === []) {
            throw new \InvalidArgumentException('RoleMiddleware requiere al menos un rol permitido.');
        }

        foreach ($allowedRoles as $role) {
            if (!in_array($role, AuthenticatedUser::ROLES, true)) {
                throw new \InvalidArgumentException("Rol desconocido: {$role}");
            }
        }

        $this->allowedRoles = array_values($allowedRoles);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute(AuthMiddleware::REQUEST_ATTR);

        if (!$user instanceof AuthenticatedUser) {
            return ApiResponse::unauthorized(new SlimResponse(), 'Sesión no autenticada.');
        }

        if (!in_array($user->role, $this->allowedRoles, true)) {
            return ApiResponse::forbidden(
                new SlimResponse(),
                'Tu rol no tiene acceso a este recurso.'
            );
        }

        return $handler->handle($request);
    }
}
