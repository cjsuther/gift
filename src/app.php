<?php

declare(strict_types=1);

use App\Auth\JwtService;
use App\Auth\PdoUserRepository;
use App\Controllers\AdminUserController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\EstablishmentController;
use App\Controllers\GiftcardController;
use App\Controllers\ProfileController;
use App\Controllers\RedemptionController;
use App\Controllers\UserController;
use App\Database\Connection;
use App\Helpers\Response as ApiResponse;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Middleware\TenantMiddleware;
use App\Repositories\EstablishmentRepository;
use App\Repositories\GiftcardRepository;
use App\Repositories\UserRepository;
use App\Services\ImageService;
use App\Services\QrService;
use App\Services\TokenService;
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

$view              = new View(__DIR__ . '/../views');
$establishmentRepo = new EstablishmentRepository($pdo);

// El uploadPath puede venir relativo (público al repo) o absoluto.
$uploadConfigured = (string) $appConfig['upload']['path'];
$uploadAbs        = str_starts_with($uploadConfigured, '/')
    ? $uploadConfigured
    : __DIR__ . '/../' . $uploadConfigured;
$imageService     = new ImageService(
    uploadsBasePath: $uploadAbs,
    maxBytes:        ((int) $appConfig['upload']['max_mb']) * 1024 * 1024,
);

$establishmentController = new EstablishmentController($establishmentRepo, $imageService);

$userTenantRepo      = new UserRepository($pdo);
$userController      = new UserController($userTenantRepo);
$adminUserController = new AdminUserController($userTenantRepo);

$giftcardRepo       = new GiftcardRepository($pdo);
$tokenService       = new TokenService();
$qrService          = new QrService((string) $appConfig['url']);
$giftcardController = new GiftcardController($giftcardRepo, $tokenService, $qrService, $imageService);

$redemptionController = new RedemptionController($giftcardRepo);
$dashboardController  = new DashboardController($giftcardRepo);
$profileController    = new ProfileController($userTenantRepo);

// 4) Slim app
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware($appConfig['debug'], true, true);

// 5) Middlewares reutilizables
$authApi = new AuthMiddleware($jwtService, $userRepository);                 // 401 JSON
$authWeb = new AuthMiddleware($jwtService, $userRepository, true);           // redirect a /login
$tenant  = new TenantMiddleware();

// 6) Rutas
//
// Convención de la cadena: AuthMiddleware → RoleMiddleware → TenantMiddleware → handler.
// Slim ejecuta los middlewares de ruta en orden INVERSO al que se agregan
// con ->add(), por eso se agregan al revés (último ->add se ejecuta primero).

// --- Auth ---
$app->post('/api/auth/login', [new AuthController($userRepository, $jwtService), 'login']);

$app->group('/api/auth', function ($g) use ($userRepository, $jwtService) {
    $controller = new AuthController($userRepository, $jwtService);
    $g->get('/me',      [$controller, 'me']);
    $g->post('/logout', [$controller, 'logout']);
})->add($authApi);

// --- Mi perfil (cualquier usuario logueado) ---
$app->put('/api/perfil', [$profileController, 'update'])->add($authApi);
$app->post('/api/perfil', [$profileController, 'update'])->add($authApi);

$app->get('/perfil', function ($req, $res) use ($view, $userTenantRepo) {
    $user    = $req->getAttribute(AuthMiddleware::REQUEST_ATTR);
    $editing = $userTenantRepo->findAny((int) $user->id);
    return $view->withLayout($res, 'auth/profile', 'layouts/admin', [
        'user'    => $user,
        'title'   => 'Mi perfil',
        'editing' => $editing,
    ]);
})->add($authWeb);

// --- Super Admin: Establecimientos (API JSON) ---
$app->group('/api/admin/establishments', function ($g) use ($establishmentController) {
    $g->get('',        [$establishmentController, 'index']);
    $g->post('',       [$establishmentController, 'store']);
    $g->get('/{id}',   [$establishmentController, 'show']);
    $g->put('/{id}',   [$establishmentController, 'update']);
    $g->post('/{id}',  [$establishmentController, 'update']);   // alias para multipart desde el browser
    $g->delete('/{id}',[$establishmentController, 'destroy']);
})->add(new RoleMiddleware('super_admin'))->add($authApi);

// --- Super Admin: Establecimientos (Vistas HTML) ---
$app->group('/admin/establishments', function ($g) use ($view, $establishmentRepo) {
    $g->get('', function ($req, $res) use ($view) {
        $user = $req->getAttribute(AuthMiddleware::REQUEST_ATTR);
        return $view->withLayout($res, 'admin/establishments/index', 'layouts/admin', [
            'user'  => $user,
            'title' => 'Establecimientos',
        ]);
    });
    $g->get('/new', function ($req, $res) use ($view) {
        $user = $req->getAttribute(AuthMiddleware::REQUEST_ATTR);
        return $view->withLayout($res, 'admin/establishments/form', 'layouts/admin', [
            'user'          => $user,
            'title'         => 'Nuevo establecimiento',
            'establishment' => null,
        ]);
    });
    $g->get('/{id}/edit', function ($req, $res, $args) use ($view, $establishmentRepo) {
        $user = $req->getAttribute(AuthMiddleware::REQUEST_ATTR);
        $row  = $establishmentRepo->find((int) $args['id']);
        if ($row === null) {
            return $res->withHeader('Location', '/admin/establishments')->withStatus(302);
        }
        return $view->withLayout($res, 'admin/establishments/form', 'layouts/admin', [
            'user'          => $user,
            'title'         => 'Editar establecimiento',
            'establishment' => $row,
        ]);
    });
})->add(new RoleMiddleware('super_admin'))->add($authWeb);

// --- Super Admin: Usuarios globales (API JSON) ---
$app->group('/api/admin/users', function ($g) use ($adminUserController) {
    $g->get('',         [$adminUserController, 'index']);
    $g->get('/{id}',    [$adminUserController, 'show']);
    $g->put('/{id}',    [$adminUserController, 'update']);
    $g->post('/{id}',   [$adminUserController, 'update']);
    $g->delete('/{id}', [$adminUserController, 'destroy']);
})->add(new RoleMiddleware('super_admin'))->add($authApi);

// --- Super Admin: Usuarios globales (Vistas HTML) ---
$app->group('/admin/users', function ($g) use ($view, $userTenantRepo) {
    $g->get('', function ($req, $res) use ($view) {
        $user = $req->getAttribute(AuthMiddleware::REQUEST_ATTR);
        return $view->withLayout($res, 'admin/users/index', 'layouts/admin', [
            'user'  => $user,
            'title' => 'Usuarios',
        ]);
    });
    $g->get('/{id}/edit', function ($req, $res, $args) use ($view, $userTenantRepo) {
        $user = $req->getAttribute(AuthMiddleware::REQUEST_ATTR);
        $row  = $userTenantRepo->findAny((int) $args['id']);
        if ($row === null) {
            return $res->withHeader('Location', '/admin/users')->withStatus(302);
        }
        return $view->withLayout($res, 'admin/users/form', 'layouts/admin', [
            'user'    => $user,
            'title'   => 'Editar usuario',
            'editing' => $row,
        ]);
    });
})->add(new RoleMiddleware('super_admin'))->add($authWeb);

// --- Establishment Admin: Usuarios (API JSON) ---
$app->group('/api/users', function ($g) use ($userController) {
    $g->get('',         [$userController, 'index']);
    $g->post('',        [$userController, 'store']);
    $g->get('/{id}',    [$userController, 'show']);
    $g->put('/{id}',    [$userController, 'update']);
    $g->post('/{id}',   [$userController, 'update']);   // alias para clientes que no manden PUT
    $g->delete('/{id}', [$userController, 'destroy']);
})
    ->add($tenant)
    ->add(new RoleMiddleware('establishment_admin'))
    ->add($authApi);

// --- Establishment Admin: Vistas HTML (Dashboard + Usuarios) ---
$app->group('', function ($g) use ($view, $userTenantRepo) {
    $g->get('/dashboard', function ($req, $res) use ($view) {
        $user = $req->getAttribute(AuthMiddleware::REQUEST_ATTR);
        return $view->withLayout($res, 'establishment/dashboard', 'layouts/admin', [
            'user'  => $user,
            'title' => 'Dashboard',
        ]);
    });
    $g->get('/users', function ($req, $res) use ($view) {
        $user = $req->getAttribute(AuthMiddleware::REQUEST_ATTR);
        return $view->withLayout($res, 'establishment/users/index', 'layouts/admin', [
            'user'  => $user,
            'title' => 'Usuarios',
        ]);
    });
    $g->get('/users/new', function ($req, $res) use ($view) {
        $user = $req->getAttribute(AuthMiddleware::REQUEST_ATTR);
        return $view->withLayout($res, 'establishment/users/form', 'layouts/admin', [
            'user'    => $user,
            'title'   => 'Nuevo usuario',
            'editing' => null,
        ]);
    });
    $g->get('/users/{id}/edit', function ($req, $res, $args) use ($view, $userTenantRepo) {
        $user = $req->getAttribute(AuthMiddleware::REQUEST_ATTR);
        $row  = $userTenantRepo->findForTenant((int) $args['id'], (int) $user->establishmentId);
        if ($row === null) {
            return $res->withHeader('Location', '/users')->withStatus(302);
        }
        return $view->withLayout($res, 'establishment/users/form', 'layouts/admin', [
            'user'    => $user,
            'title'   => 'Editar usuario',
            'editing' => $row,
        ]);
    });
})
    ->add($tenant)
    ->add(new RoleMiddleware('establishment_admin'))
    ->add($authWeb);

// --- Giftcards: lectura compartida (admin + user del establecimiento) ---
$app->group('/api/giftcards', function ($g) use ($giftcardController) {
    $g->get('',         [$giftcardController, 'index']);
    $g->get('/{id}',    [$giftcardController, 'show']);
})
    ->add($tenant)
    ->add(new RoleMiddleware('establishment_admin', 'establishment_user'))
    ->add($authApi);

// --- Giftcards: mutación + QR (solo admin del establecimiento) ---
$app->group('/api/giftcards', function ($g) use ($giftcardController) {
    $g->post('',         [$giftcardController, 'store']);
    $g->put('/{id}',     [$giftcardController, 'update']);
    $g->post('/{id}',    [$giftcardController, 'update']);   // alias para multipart
    $g->delete('/{id}',  [$giftcardController, 'destroy']);
    $g->get('/{id}/qr',  [$giftcardController, 'qr']);
})
    ->add($tenant)
    ->add(new RoleMiddleware('establishment_admin'))
    ->add($authApi);

// --- Giftcards: vistas HTML (admin + user en modo lectura) ---
$app->group('', function ($g) use ($view, $giftcardRepo, $qrService, $establishmentRepo) {
    $g->get('/giftcards', function ($req, $res) use ($view) {
        $user = $req->getAttribute(AuthMiddleware::REQUEST_ATTR);
        return $view->withLayout($res, 'establishment/giftcards/index', 'layouts/admin', [
            'user'  => $user,
            'title' => 'Giftcards',
        ]);
    });
    $g->get('/giftcards/new', function ($req, $res) use ($view) {
        $user = $req->getAttribute(AuthMiddleware::REQUEST_ATTR);
        if (!$user->isEstablishmentAdmin()) {
            return $res->withHeader('Location', '/giftcards')->withStatus(302);
        }
        return $view->withLayout($res, 'establishment/giftcards/form', 'layouts/admin', [
            'user'    => $user,
            'title'   => 'Nueva giftcard',
            'editing' => null,
        ]);
    });
    $g->get('/giftcards/{id}', function ($req, $res, $args) use ($view, $giftcardRepo, $qrService) {
        $user = $req->getAttribute(AuthMiddleware::REQUEST_ATTR);
        $row  = $giftcardRepo->findForTenant((int) $args['id'], (int) $user->establishmentId);
        if ($row === null) {
            return $res->withHeader('Location', '/giftcards')->withStatus(302);
        }
        return $view->withLayout($res, 'establishment/giftcards/show', 'layouts/admin', [
            'user'       => $user,
            'title'      => $row['title'],
            'giftcard'   => $row,
            'redeemUrl'  => $qrService->urlForToken((string) $row['token']),
            'tokenShort' => \App\Services\TokenService::shortLabel((string) $row['token']),
            'qrDataUri'  => $qrService->dataUriForToken((string) $row['token']),
        ]);
    });
    $g->get('/giftcards/{id}/print', function ($req, $res, $args) use ($view, $giftcardRepo, $qrService, $establishmentRepo) {
        $user = $req->getAttribute(AuthMiddleware::REQUEST_ATTR);
        $row  = $giftcardRepo->findForTenant((int) $args['id'], (int) $user->establishmentId);
        if ($row === null) {
            return $res->withHeader('Location', '/giftcards')->withStatus(302);
        }
        $est = $establishmentRepo->find((int) $user->establishmentId);
        if ($est === null) {
            return $res->withHeader('Location', '/giftcards')->withStatus(302);
        }
        // No usamos layout — la vista de print es standalone con su propio HTML.
        $html = (new \App\Helpers\View(__DIR__ . '/../views'))->render(
            $res,
            'establishment/giftcards/print',
            [
                'giftcard'      => $row,
                'establishment' => $est,
                'qrDataUri'     => $qrService->dataUriForToken((string) $row['token']),
                'tokenShort'    => \App\Services\TokenService::shortLabel((string) $row['token']),
                'redeemUrl'     => $qrService->urlForToken((string) $row['token']),
            ]
        );
        return $html;
    });
    $g->get('/giftcards/{id}/edit', function ($req, $res, $args) use ($view, $giftcardRepo) {
        $user = $req->getAttribute(AuthMiddleware::REQUEST_ATTR);
        if (!$user->isEstablishmentAdmin()) {
            return $res->withHeader('Location', '/giftcards/' . (int) $args['id'])->withStatus(302);
        }
        $row = $giftcardRepo->findForTenant((int) $args['id'], (int) $user->establishmentId);
        if ($row === null) {
            return $res->withHeader('Location', '/giftcards')->withStatus(302);
        }
        return $view->withLayout($res, 'establishment/giftcards/form', 'layouts/admin', [
            'user'    => $user,
            'title'   => 'Editar giftcard',
            'editing' => $row,
        ]);
    });
})
    ->add($tenant)
    ->add(new RoleMiddleware('establishment_admin', 'establishment_user'))
    ->add($authWeb);

// --- Dashboard (API) — solo establishment_admin ---
$app->group('/api/dashboard', function ($g) use ($dashboardController) {
    $g->get('/stats', [$dashboardController, 'stats']);
})
    ->add($tenant)
    ->add(new RoleMiddleware('establishment_admin'))
    ->add($authApi);

// --- Canje (API) ---
$app->group('/api/redeem', function ($g) use ($redemptionController) {
    $g->get('/lookup',    [$redemptionController, 'lookupShort']);
    $g->post('/{token}',  [$redemptionController, 'redeem']);
})
    ->add($tenant)
    ->add(new RoleMiddleware('establishment_admin', 'establishment_user'))
    ->add($authApi);

// --- Canje (Vistas HTML) ---
$app->group('', function ($g) use ($view, $giftcardRepo) {
    $g->get('/scan', function ($req, $res) use ($view) {
        $user = $req->getAttribute(AuthMiddleware::REQUEST_ATTR);
        return $view->withLayout($res, 'establishment/scan', 'layouts/admin', [
            'user'  => $user,
            'title' => 'Escanear',
        ]);
    });
    $g->get('/redeem/{token}', function ($req, $res, $args) use ($view, $giftcardRepo) {
        $user  = $req->getAttribute(AuthMiddleware::REQUEST_ATTR);
        $token = (string) $args['token'];
        $row   = preg_match('/^[a-f0-9]{32}$/', $token) === 1
            ? $giftcardRepo->findByTokenForTenant($token, (int) $user->establishmentId)
            : null;
        return $view->withLayout($res, 'establishment/redeem/show', 'layouts/admin', [
            'user'                => $user,
            'title'               => $row['title'] ?? 'Canje',
            'giftcard'            => $row,
            'token'               => $token,
            'crossTenantMessage'  => null,
        ]);
    });
})
    ->add($tenant)
    ->add(new RoleMiddleware('establishment_admin', 'establishment_user'))
    ->add($authWeb);

// --- Healthcheck público ---
$app->get('/api/health', function ($req, $res) {
    return ApiResponse::json($res, ['status' => 'ok', 'time' => date(DATE_ATOM)]);
});

// --- Vista de login (HTML, pública) ---
$app->get('/login', function ($req, $res) {
    $html = file_get_contents(__DIR__ . '/../views/auth/login.php');
    $res->getBody()->write($html === false ? 'Vista no encontrada' : $html);
    return $res->withHeader('Content-Type', 'text/html; charset=utf-8');
});

return $app;
