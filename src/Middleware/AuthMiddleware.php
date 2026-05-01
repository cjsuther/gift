<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\JwtService;
use App\Auth\UserProvider;
use App\Helpers\Response as ApiResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as SlimResponse;

/**
 * Lee el JWT del header Authorization (o de la cookie `auth_token`),
 * lo valida, busca el usuario en la DB y lo inyecta como atributo
 * `user` (instancia de AuthenticatedUser) en el request.
 *
 * Si falla la validación devuelve 401. NO toca roles ni tenancy:
 * eso lo hacen RoleMiddleware y TenantMiddleware encadenados después.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public const REQUEST_ATTR = 'user';

    public function __construct(
        private readonly JwtService $jwt,
        private readonly UserProvider $users,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->extractToken($request);
        if ($token === null) {
            return $this->reject('Token no provisto.');
        }

        try {
            $payload = $this->jwt->decode($token);
        } catch (\Throwable) {
            return $this->reject('Token inválido o expirado.');
        }

        $user = $this->users->findActiveById($payload['sub']);
        if ($user === null) {
            return $this->reject('Usuario no encontrado o inactivo.');
        }

        // Defensa en profundidad: el rol del JWT debe coincidir con el de la DB
        // (evita usar tokens viejos si al usuario le cambiaron el rol).
        if ($user->role !== ($payload['role'] ?? null)) {
            return $this->reject('Rol del token no coincide con el actual.');
        }

        $request = $request->withAttribute(self::REQUEST_ATTR, $user);

        return $handler->handle($request);
    }

    private function extractToken(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        if ($header !== '' && preg_match('/Bearer\s+(.+)/i', $header, $m) === 1) {
            return trim($m[1]);
        }

        $cookies = $request->getCookieParams();
        if (!empty($cookies['auth_token'])) {
            return (string) $cookies['auth_token'];
        }

        return null;
    }

    private function reject(string $message): ResponseInterface
    {
        return ApiResponse::unauthorized(new SlimResponse(), $message);
    }
}
