<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthenticatedUser;
use App\Auth\JwtService;
use App\Auth\PdoUserRepository;
use App\Helpers\Response as ApiResponse;
use App\Helpers\Validator;
use App\Middleware\AuthMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    public function __construct(
        private readonly PdoUserRepository $users,
        private readonly JwtService $jwt,
        private readonly int $rememberTtlHours = 720,   // 30 días por default
    ) {
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->parseBody($request);

        $v = (new Validator($body))
            ->required('email')->email('email')
            ->required('password')->minLength('password', 6);

        if ($v->fails()) {
            return ApiResponse::error($response, 'Datos inválidos.', 422, ['fields' => $v->errors()]);
        }

        $row = $this->users->findActiveByEmail((string) $body['email']);
        if ($row === null || !password_verify((string) $body['password'], (string) $row['password_hash'])) {
            // Mensaje genérico para no filtrar si el email existe.
            return ApiResponse::error($response, 'Credenciales inválidas.', 401);
        }

        $user = $this->users->hydrate($row);
        $this->users->touchLastLogin($user->id);

        // remember=true (default): JWT y cookie de larga duración.
        // remember=false: JWT corto (default del JwtService) + cookie de sesión.
        $remember = $this->parseBool($body['remember'] ?? true);
        $ttlHours = $remember ? $this->rememberTtlHours : null;

        $token       = $this->jwt->issue($user->id, $user->role, $user->establishmentId, $ttlHours);
        $cookieValue = $this->buildAuthCookie($token, $remember);

        return ApiResponse::json(
            $response->withAddedHeader('Set-Cookie', $cookieValue),
            [
                'token'    => $token,
                'user'     => $user->toArray(),
                'remember' => $remember,
            ]
        );
    }

    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $request->getAttribute(AuthMiddleware::REQUEST_ATTR);
        if (!$user instanceof AuthenticatedUser) {
            return ApiResponse::unauthorized($response);
        }

        return ApiResponse::json($response, ['user' => $user->toArray()]);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // JWT es stateless: el "logout" se hace borrando el token en el cliente.
        // Igual respondemos 200 y limpiamos la cookie por las dudas.
        $clear = 'auth_token=; Path=/; Max-Age=0; HttpOnly; Secure; SameSite=Lax';
        return ApiResponse::json(
            $response->withHeader('Set-Cookie', $clear),
            ['ok' => true]
        );
    }

    /**
     * Construye el header Set-Cookie para el JWT.
     * - HttpOnly: no accesible desde JS (defensa contra XSS).
     * - Secure: solo se manda por HTTPS.
     * - SameSite=Lax: protección CSRF para POSTs cross-site.
     * - Max-Age: solo si remember=true (cookie persistente).
     *   Sin Max-Age → cookie de sesión (se borra al cerrar el browser).
     */
    private function buildAuthCookie(string $token, bool $remember): string
    {
        $parts = ["auth_token={$token}", 'Path=/', 'HttpOnly', 'Secure', 'SameSite=Lax'];
        if ($remember) {
            $parts[] = 'Max-Age=' . ($this->rememberTtlHours * 3600);
        }
        return implode('; ', $parts);
    }

    private function parseBool(mixed $value): bool
    {
        if (is_bool($value))   return $value;
        if (is_int($value))    return $value !== 0;
        if (is_string($value)) return in_array(strtolower($value), ['1', 'true', 'on', 'yes', 'si'], true);
        return (bool) $value;
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
