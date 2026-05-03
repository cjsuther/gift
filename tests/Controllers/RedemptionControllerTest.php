<?php

declare(strict_types=1);

namespace App\Tests\Controllers;

use App\Auth\AuthenticatedUser;
use App\Controllers\RedemptionController;
use App\Middleware\AuthMiddleware;
use App\Middleware\TenantMiddleware;
use App\Repositories\GiftcardRepository;
use App\Tests\Support\TestRequest;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;

final class RedemptionControllerTest extends TestCase
{
    private const TENANT  = 5;
    private const USER_ID = 21;
    private const TOKEN   = 'a1b2c3d4e5f6071829304a5b6c7d8e9f';

    private function user(string $role = 'establishment_user'): AuthenticatedUser
    {
        return new AuthenticatedUser(self::USER_ID, $role, self::TENANT, 'u@x.com', 'User');
    }

    private function withAuth(\Psr\Http\Message\ServerRequestInterface $req): \Psr\Http\Message\ServerRequestInterface
    {
        return $req
            ->withAttribute(AuthMiddleware::REQUEST_ATTR, $this->user())
            ->withAttribute(TenantMiddleware::REQUEST_ATTR, self::TENANT);
    }

    public function test_redeem_400_si_token_no_es_32_hex(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->expects($this->never())->method('findByTokenForTenant');

        $controller = new RedemptionController($repo);

        $req = $this->withAuth(TestRequest::create('POST', '/api/redeem/no-valido'));
        $res = $controller->redeem($req, new Response(), ['token' => 'no-valido']);

        $this->assertSame(400, $res->getStatusCode());
    }

    public function test_redeem_404_si_token_no_pertenece_al_tenant(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        // findByTokenForTenant collapses "no existe" y "otro tenant" en null.
        $repo->method('findByTokenForTenant')->willReturn(null);
        $repo->expects($this->never())->method('redeem');

        $controller = new RedemptionController($repo);

        $req = $this->withAuth(TestRequest::create('POST', '/api/redeem/' . self::TOKEN));
        $res = $controller->redeem($req, new Response(), ['token' => self::TOKEN]);

        $this->assertSame(404, $res->getStatusCode());
    }

    public function test_redeem_422_si_ya_estaba_canjeada(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->method('findByTokenForTenant')->willReturn([
            'id' => 7, 'token' => self::TOKEN, 'status' => 'redeemed',
            'redeemed_at' => '2026-05-01 12:00:00',
            'redeemed_by_name' => 'Otro Op',
        ]);
        $repo->expects($this->never())->method('redeem');

        $controller = new RedemptionController($repo);

        $req = $this->withAuth(TestRequest::create('POST', '/api/redeem/' . self::TOKEN));
        $res = $controller->redeem($req, new Response(), ['token' => self::TOKEN]);

        $this->assertSame(422, $res->getStatusCode());
        $body = json_decode((string) $res->getBody(), true);
        $this->assertStringContainsString('ya fue canjeada', $body['error']);
    }

    public function test_redeem_422_si_esta_cancelada(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->method('findByTokenForTenant')->willReturn([
            'id' => 7, 'token' => self::TOKEN, 'status' => 'cancelled',
        ]);
        $repo->expects($this->never())->method('redeem');

        $controller = new RedemptionController($repo);

        $req = $this->withAuth(TestRequest::create('POST', '/api/redeem/' . self::TOKEN));
        $res = $controller->redeem($req, new Response(), ['token' => self::TOKEN]);

        $this->assertSame(422, $res->getStatusCode());
    }

    public function test_redeem_422_si_la_giftcard_esta_vencida_por_status(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->method('findByTokenForTenant')->willReturn([
            'id' => 7, 'token' => self::TOKEN, 'status' => 'expired',
        ]);
        $repo->expects($this->never())->method('redeem');

        $controller = new RedemptionController($repo);

        $req = $this->withAuth(TestRequest::create('POST', '/api/redeem/' . self::TOKEN));
        $res = $controller->redeem($req, new Response(), ['token' => self::TOKEN]);

        $this->assertSame(422, $res->getStatusCode());
    }

    public function test_redeem_422_si_la_fecha_de_vencimiento_ya_paso_aunque_status_sea_active(): void
    {
        // El cron de Fase 8 todavía no marcó status=expired, pero la fecha pasó.
        // Igual la rechazamos.
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->method('findByTokenForTenant')->willReturn([
            'id' => 7, 'token' => self::TOKEN, 'status' => 'active',
            'expires_at' => '2020-01-01',
        ]);
        $repo->expects($this->never())->method('redeem');

        $controller = new RedemptionController($repo);

        $req = $this->withAuth(TestRequest::create('POST', '/api/redeem/' . self::TOKEN));
        $res = $controller->redeem($req, new Response(), ['token' => self::TOKEN]);

        $this->assertSame(422, $res->getStatusCode());
    }

    public function test_redeem_exitoso_si_esta_active_y_no_vencida(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->method('findByTokenForTenant')->willReturn([
            'id' => 7, 'token' => self::TOKEN, 'status' => 'active',
            'expires_at' => null,
        ]);
        $repo->expects($this->once())
             ->method('redeem')
             ->with(7, self::TENANT, self::USER_ID, $this->anything(), $this->anything())
             ->willReturn(true);

        $controller = new RedemptionController($repo);

        $req = $this->withAuth(TestRequest::create('POST', '/api/redeem/' . self::TOKEN));
        $res = $controller->redeem($req, new Response(), ['token' => self::TOKEN]);

        $this->assertSame(200, $res->getStatusCode());
        $body = json_decode((string) $res->getBody(), true);
        $this->assertTrue($body['ok']);
    }

    public function test_redeem_409_si_race_condition(): void
    {
        // Otro request canjeó entre el find y el update.
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->method('findByTokenForTenant')->willReturn([
            'id' => 7, 'token' => self::TOKEN, 'status' => 'active',
            'expires_at' => null,
        ]);
        $repo->method('redeem')->willReturn(false);

        $controller = new RedemptionController($repo);

        $req = $this->withAuth(TestRequest::create('POST', '/api/redeem/' . self::TOKEN));
        $res = $controller->redeem($req, new Response(), ['token' => self::TOKEN]);

        $this->assertSame(409, $res->getStatusCode());
    }

    public function test_lookupShort_devuelve_token_si_hay_match_unico(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->method('findByShortTokenForTenant')->with('a1b2c3d4', self::TENANT)
             ->willReturn(['token' => self::TOKEN, 'id' => 7]);

        $controller = new RedemptionController($repo);

        $req = $this->withAuth(TestRequest::create('GET', '/api/redeem/lookup'))
            ->withQueryParams(['short' => 'a1b2c3d4']);
        $res = $controller->lookupShort($req, new Response());

        $this->assertSame(200, $res->getStatusCode());
        $body = json_decode((string) $res->getBody(), true);
        $this->assertSame(self::TOKEN, $body['token']);
    }

    public function test_lookupShort_404_si_no_existe(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->method('findByShortTokenForTenant')->willReturn(null);

        $controller = new RedemptionController($repo);

        $req = $this->withAuth(TestRequest::create('GET', '/api/redeem/lookup'))
            ->withQueryParams(['short' => 'aaaaaaaa']);
        $res = $controller->lookupShort($req, new Response());

        $this->assertSame(404, $res->getStatusCode());
    }

    public function test_lookupShort_409_si_ambiguo(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->method('findByShortTokenForTenant')->willReturn([]); // varios matches

        $controller = new RedemptionController($repo);

        $req = $this->withAuth(TestRequest::create('GET', '/api/redeem/lookup'))
            ->withQueryParams(['short' => 'a1b2']);
        $res = $controller->lookupShort($req, new Response());

        $this->assertSame(409, $res->getStatusCode());
    }
}
