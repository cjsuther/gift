<?php

declare(strict_types=1);

namespace App\Tests\Controllers;

use App\Controllers\DashboardController;
use App\Middleware\TenantMiddleware;
use App\Repositories\GiftcardRepository;
use App\Tests\Support\TestRequest;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;

final class DashboardControllerTest extends TestCase
{
    private const TENANT = 5;

    public function test_stats_devuelve_kpis_recent_created_y_recent_redeemed(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);

        $repo->expects($this->once())
             ->method('statsForTenant')
             ->with(self::TENANT)
             ->willReturn([
                 'counts' => ['active' => 10, 'redeemed' => 5, 'expired' => 1, 'cancelled' => 2, 'total' => 18],
                 'redeemed_this_month' => 3,
                 'created_this_month'  => 6,
                 'redemption_rate'     => 27.8,
             ]);

        $repo->expects($this->once())
             ->method('recentCreatedForTenant')
             ->with(self::TENANT, 5)
             ->willReturn([['id' => 1, 'title' => 'Combo']]);

        $repo->expects($this->once())
             ->method('recentRedeemedForTenant')
             ->with(self::TENANT, 5)
             ->willReturn([['id' => 2, 'title' => 'Pizza', 'redeemed_by_name' => 'Pedro']]);

        $controller = new DashboardController($repo);

        $req = TestRequest::create('GET', '/api/dashboard/stats')
            ->withAttribute(TenantMiddleware::REQUEST_ATTR, self::TENANT);
        $res = $controller->stats($req, new Response());

        $this->assertSame(200, $res->getStatusCode());
        $body = json_decode((string) $res->getBody(), true);
        $this->assertSame(18, $body['stats']['counts']['total']);
        $this->assertSame(27.8, $body['stats']['redemption_rate']);
        $this->assertCount(1, $body['recent_created']);
        $this->assertSame('Pedro', $body['recent_redeemed'][0]['redeemed_by_name']);
    }

    public function test_stats_pasa_el_tenant_del_request_no_uno_arbitrario(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->expects($this->once())->method('statsForTenant')->with(99);
        $repo->method('recentCreatedForTenant')->willReturn([]);
        $repo->method('recentRedeemedForTenant')->willReturn([]);

        $controller = new DashboardController($repo);

        $req = TestRequest::create('GET', '/api/dashboard/stats')
            ->withAttribute(TenantMiddleware::REQUEST_ATTR, 99)
            ->withQueryParams(['establishment_id' => 1]);   // intento de override (debe ignorarse)

        $controller->stats($req, new Response());
    }
}
