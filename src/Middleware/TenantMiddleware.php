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
 * Garantiza que la request opera dentro de un establecimiento.
 *
 * - Rechaza requests sin usuario autenticado (401).
 * - Rechaza super_admin: el rol no opera giftcards/usuarios de un
 *   establecimiento (403). Si el super_admin necesita actuar sobre
 *   un est., debe loguearse con un usuario del mismo (ver spec §1).
 * - Rechaza usuarios sin establishment_id (defensivo, no debería pasar).
 *
 * Inyecta `establishment_id` en el request para que repos/controllers
 * lo usen como WHERE filter sin excepción. Esta es la fuente de verdad
 * del tenant: NUNCA leer establishment_id del body, query o params.
 */
final class TenantMiddleware implements MiddlewareInterface
{
    public const REQUEST_ATTR = 'establishment_id';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute(AuthMiddleware::REQUEST_ATTR);

        if (!$user instanceof AuthenticatedUser) {
            return ApiResponse::unauthorized(new SlimResponse(), 'Sesión no autenticada.');
        }

        if ($user->isSuperAdmin()) {
            return ApiResponse::forbidden(
                new SlimResponse(),
                'super_admin no puede operar sobre recursos de un establecimiento.'
            );
        }

        if ($user->establishmentId === null) {
            return ApiResponse::forbidden(
                new SlimResponse(),
                'Usuario sin establecimiento asignado.'
            );
        }

        $request = $request->withAttribute(self::REQUEST_ATTR, $user->establishmentId);

        return $handler->handle($request);
    }

    /**
     * Helper para usar en controllers: verifica que un recurso ya cargado
     * pertenezca al establecimiento del usuario logueado. Centraliza el
     * check para que no se filtre por error en ningún endpoint.
     *
     * Devuelve true si pertenece. Si false → el controller debe responder 403.
     */
    public static function ownsResource(ServerRequestInterface $request, ?int $resourceEstablishmentId): bool
    {
        if ($resourceEstablishmentId === null) {
            return false;
        }
        $tenant = $request->getAttribute(self::REQUEST_ATTR);
        return is_int($tenant) && $tenant === $resourceEstablishmentId;
    }
}
