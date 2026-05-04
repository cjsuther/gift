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
        private readonly bool $redirectToLoginOnFailure = false,
        private readonly bool $optional = false,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->extractToken($request);
        if ($token === null) {
            return $this->fail('Token no provisto.', $request, $handler);
        }

        try {
            $payload = $this->jwt->decode($token);
        } catch (\Throwable) {
            return $this->fail('Token inválido o expirado.', $request, $handler);
        }

        $user = $this->users->findActiveById($payload['sub']);
        if ($user === null) {
            return $this->fail('Usuario no encontrado o inactivo.', $request, $handler);
        }

        // Defensa en profundidad: el rol del JWT debe coincidir con el de la DB
        // (evita usar tokens viejos si al usuario le cambiaron el rol).
        if ($user->role !== ($payload['role'] ?? null)) {
            return $this->fail('Rol del token no coincide con el actual.', $request, $handler);
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

    /**
     * Decide qué hacer cuando la autenticación falla:
     *  - Si optional=true: continúa sin user inyectado (handler decide qué hacer).
     *  - Si redirectToLoginOnFailure=true: 302 a /login?next=URL-original.
     *  - Default: 401 JSON.
     */
    private function fail(string $message, ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->optional) {
            return $handler->handle($request);
        }
        if ($this->redirectToLoginOnFailure) {
            $location = '/login' . $this->buildNextQuery($request);
            return (new SlimResponse())
                ->withHeader('Location', $location)
                ->withStatus(302);
        }
        return ApiResponse::unauthorized(new SlimResponse(), $message);
    }

    /**
     * Devuelve "?next=..." con la URL original para que el form de login
     * pueda redirigir ahí después del login. Solo path + query (sin host).
     * Si la ruta es /login (loop) o root (/), no agrega next.
     */
    private function buildNextQuery(ServerRequestInterface $request): string
    {
        $uri  = $request->getUri();
        $path = $uri->getPath();
        if ($path === '' || $path === '/' || $path === '/login') {
            return '';
        }
        $next = $path;
        $qs   = $uri->getQuery();
        if ($qs !== '') {
            $next .= '?' . $qs;
        }
        return '?next=' . rawurlencode($next);
    }
}
