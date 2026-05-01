<?php

declare(strict_types=1);

use App\Auth\JwtService;
use App\Auth\PdoUserRepository;
use App\Controllers\AuthController;
use App\Database\Connection;
use App\Helpers\Response as ApiResponse;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\TenantMiddleware;
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// 1) Variables de entorno
Dotenv::createImmutable(__DIR__ . '/..')->load();

// 2) Config
$appConfig = require __DIR__ . '/../config/app.php';

// 3) Servicios "DI manual" (Phase 1: sin contenedor formal todavía)
$pdo            = Connection::get();
$userRepository = new PdoUserRepository($pdo);
$jwtService     = new JwtService(
    secret:    (string) $appConfig['jwt']['secret'],
    ttlHours:  (int) $appConfig['jwt']['ttl_hours'],
    issuer:    (string) $appConfig['jwt']['issuer'],
    algo:      (string) $appConfig['jwt']['algo'],
);

// 4) Slim app
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware($appConfig['debug'], true, true);

// 5) Middlewares reutilizables
$auth   = new AuthMiddleware($jwtService, $userRepository);
$tenant = new TenantMiddleware();

// 6) Rutas
//
// Convención de la cadena: AuthMiddleware → RoleMiddleware → TenantMiddleware → handler.
// Slim ejecuta los middlewares de ruta en orden INVERSO al que se agregan
// con ->add(), por eso se agregan al revés (último ->add se ejecuta primero).

$app->post('/api/auth/login', [new AuthController($userRepository, $jwtService), 'login']);

$app->group('/api/auth', function ($g) use ($userRepository, $jwtService) {
    $controller = new AuthController($userRepository, $jwtService);
    $g->get('/me',      [$controller, 'me']);
    $g->post('/logout', [$controller, 'logout']);
})->add($auth);

// Ejemplo: rutas exclusivas de super_admin (Fase 2 las completará)
$app->group('/api/admin', function ($g) {
    $g->get('/establishments', function ($req, $res) {
        return ApiResponse::json($res, ['placeholder' => 'Fase 2']);
    });
})->add(new RoleMiddleware('super_admin'))->add($auth);

// Ejemplo: rutas tenant-scoped (admin o user del establecimiento)
// AuthMiddleware → RoleMiddleware → TenantMiddleware → handler
$app->group('/api/giftcards', function ($g) {
    $g->get('', function ($req, $res) {
        $tenantId = $req->getAttribute(TenantMiddleware::REQUEST_ATTR);
        return ApiResponse::json($res, [
            'placeholder' => 'Fase 4',
            'establishment_id' => $tenantId,
        ]);
    });
})
    ->add($tenant)
    ->add(new RoleMiddleware('establishment_admin', 'establishment_user'))
    ->add($auth);

// Healthcheck público
$app->get('/api/health', function ($req, $res) {
    return ApiResponse::json($res, ['status' => 'ok', 'time' => date(DATE_ATOM)]);
});

// Vista de login (HTML)
$app->get('/login', function ($req, $res) {
    $html = file_get_contents(__DIR__ . '/../views/auth/login.php');
    $res->getBody()->write($html === false ? 'Vista no encontrada' : $html);
    return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
});

return $app;
