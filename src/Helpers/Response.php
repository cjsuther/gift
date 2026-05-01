<?php

declare(strict_types=1);

namespace App\Helpers;

use Psr\Http\Message\ResponseInterface;

final class Response
{
    public static function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $response->getBody()->write($payload === false ? '{}' : $payload);

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }

    public static function error(ResponseInterface $response, string $message, int $status = 400, array $extra = []): ResponseInterface
    {
        return self::json($response, array_merge(['error' => $message], $extra), $status);
    }

    public static function unauthorized(ResponseInterface $response, string $message = 'No autorizado'): ResponseInterface
    {
        return self::error($response, $message, 401);
    }

    public static function forbidden(ResponseInterface $response, string $message = 'Acceso denegado'): ResponseInterface
    {
        return self::error($response, $message, 403);
    }

    public static function notFound(ResponseInterface $response, string $message = 'No encontrado'): ResponseInterface
    {
        return self::error($response, $message, 404);
    }
}
