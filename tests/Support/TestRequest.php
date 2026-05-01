<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Request;

final class TestRequest
{
    public static function create(string $method = 'GET', string $uri = '/', array $headers = [], array $cookies = []): Request
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($cookies !== []) {
            $request = $request->withCookieParams($cookies);
        }
        /** @var Request $request */
        return $request;
    }
}
