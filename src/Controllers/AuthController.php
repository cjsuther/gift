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

        $token = $this->jwt->issue($user->id, $user->role, $user->establishmentId);

        return ApiResponse::json($response, [
            'token' => $token,
            'user'  => $user->toArray(),
        ]);
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
        return ApiResponse::json(
            $response->withHeader('Set-Cookie', 'auth_token=; Path=/; Max-Age=0; HttpOnly; SameSite=Lax'),
            ['ok' => true]
        );
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
