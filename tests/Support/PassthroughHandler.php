<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as SlimResponse;

/**
 * Handler de prueba que captura el request final que llega al "controller"
 * y devuelve un 200 con un payload predecible. Permite verificar qué atributos
 * inyectaron los middlewares antes de llegar al final de la cadena.
 */
final class PassthroughHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $lastRequest = null;
    public bool $wasCalled = false;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->wasCalled = true;
        $this->lastRequest = $request;

        $response = new SlimResponse();
        $response->getBody()->write(json_encode(['ok' => true]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }
}
