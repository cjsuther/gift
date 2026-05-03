<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response as ApiResponse;
use App\Middleware\TenantMiddleware;
use App\Repositories\GiftcardRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * KPIs y resumen del establishment_admin para la pantalla /dashboard.
 * Solo lectura, no expone acciones — sirve API que la vista consume vía fetch.
 */
final class DashboardController
{
    public function __construct(private readonly GiftcardRepository $repo)
    {
    }

    public function stats(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenant = $this->tenant($request);

        return ApiResponse::json($response, [
            'stats'             => $this->repo->statsForTenant($tenant),
            'recent_created'    => $this->repo->recentCreatedForTenant($tenant, 5),
            'recent_redeemed'   => $this->repo->recentRedeemedForTenant($tenant, 5),
        ]);
    }

    private function tenant(ServerRequestInterface $request): int
    {
        $t = $request->getAttribute(TenantMiddleware::REQUEST_ATTR);
        if (!is_int($t)) {
            throw new \RuntimeException('Tenant no inyectado.');
        }
        return $t;
    }
}
