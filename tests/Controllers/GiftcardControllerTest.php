<?php

declare(strict_types=1);

namespace App\Tests\Controllers;

use App\Auth\AuthenticatedUser;
use App\Controllers\GiftcardController;
use App\Middleware\AuthMiddleware;
use App\Middleware\TenantMiddleware;
use App\Repositories\GiftcardRepository;
use App\Services\QrService;
use App\Services\TokenService;
use App\Tests\Support\TestRequest;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;

final class GiftcardControllerTest extends TestCase
{
    private const TENANT     = 5;
    private const USER_ID    = 21;

    private function admin(): AuthenticatedUser
    {
        return new AuthenticatedUser(self::USER_ID, 'establishment_admin', self::TENANT, 'a@x.com', 'Admin');
    }

    private function withAuth(\Psr\Http\Message\ServerRequestInterface $req): \Psr\Http\Message\ServerRequestInterface
    {
        return $req
            ->withAttribute(AuthMiddleware::REQUEST_ATTR, $this->admin())
            ->withAttribute(TenantMiddleware::REQUEST_ATTR, self::TENANT);
    }

    private function controller(GiftcardRepository $repo): GiftcardController
    {
        return new GiftcardController(
            $repo,
            new TokenService(),
            new QrService('https://example.com'),
            null,   // sin ImageService → no se procesan imágenes
        );
    }

    public function test_store_rechaza_422_si_falta_el_titulo(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->expects($this->never())->method('create');

        $req = $this->withAuth(TestRequest::create('POST', '/api/giftcards'))
            ->withParsedBody(['description' => 'Sin título']);

        $res = $this->controller($repo)->store($req, new Response());
        $this->assertSame(422, $res->getStatusCode());
    }

    public function test_store_rechaza_si_la_fecha_de_vencimiento_es_invalida(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->expects($this->never())->method('create');

        $req = $this->withAuth(TestRequest::create('POST', '/api/giftcards'))
            ->withParsedBody([
                'title'      => 'Combo',
                'expires_at' => '31/12/2025',  // formato inválido
            ]);

        $res  = $this->controller($repo)->store($req, new Response());
        $body = json_decode((string) $res->getBody(), true);
        $this->assertSame(422, $res->getStatusCode());
        $this->assertArrayHasKey('expires_at', $body['fields']);
    }

    public function test_store_genera_token_y_crea_con_tenant_del_request(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->method('tokenExists')->willReturn(false);

        // CRITICAL: el repo recibe TENANT y USER_ID del request, no del body.
        $repo->expects($this->once())
             ->method('create')
             ->with(
                 self::TENANT,
                 self::USER_ID,
                 $this->matchesRegularExpression('/^[a-f0-9]{32}$/'),
                 $this->callback(fn (array $d) => $d['title'] === 'Combo'),
                 $this->isNull(),
             )
             ->willReturn(101);

        $repo->method('findForTenant')->willReturn([
            'id' => 101, 'token' => 'a1b2c3d4e5f6071829304a5b6c7d8e9f',
            'title' => 'Combo', 'status' => 'active', 'image_path' => null,
        ]);

        $req = $this->withAuth(TestRequest::create('POST', '/api/giftcards'))
            ->withParsedBody([
                'title'            => 'Combo',
                'establishment_id' => 999,                  // ignorado
                'created_by_user_id' => 88,                 // ignorado
            ]);

        $res  = $this->controller($repo)->store($req, new Response());
        $body = json_decode((string) $res->getBody(), true);

        $this->assertSame(201, $res->getStatusCode());
        $this->assertSame(101, $body['giftcard']['id']);
        $this->assertSame('A1B2C3D4', $body['token_short']);
        $this->assertStringEndsWith('/redeem/a1b2c3d4e5f6071829304a5b6c7d8e9f', $body['redeem_url']);
    }

    public function test_update_rechaza_si_no_esta_activa(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->method('findForTenant')->willReturn([
            'id' => 9, 'token' => 't', 'title' => 'X', 'status' => 'redeemed', 'image_path' => null,
        ]);
        $repo->expects($this->never())->method('updateActive');

        $req = $this->withAuth(TestRequest::create('PUT', '/api/giftcards/9'))
            ->withParsedBody(['title' => 'Nuevo']);

        $res = $this->controller($repo)->update($req, new Response(), ['id' => '9']);
        $this->assertSame(422, $res->getStatusCode());
    }

    public function test_destroy_rechaza_si_no_esta_activa(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->method('findForTenant')->willReturn([
            'id' => 9, 'token' => 't', 'title' => 'X', 'status' => 'cancelled', 'image_path' => null,
        ]);
        $repo->expects($this->never())->method('cancel');

        $req = $this->withAuth(TestRequest::create('DELETE', '/api/giftcards/9'));
        $res = $this->controller($repo)->destroy($req, new Response(), ['id' => '9']);
        $this->assertSame(422, $res->getStatusCode());
    }

    public function test_destroy_cancela_si_esta_activa(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->method('findForTenant')->willReturn([
            'id' => 9, 'token' => 't', 'title' => 'X', 'status' => 'active', 'image_path' => null,
        ]);
        $repo->expects($this->once())->method('cancel')->with(9, self::TENANT, self::USER_ID);

        $req = $this->withAuth(TestRequest::create('DELETE', '/api/giftcards/9'));
        $res = $this->controller($repo)->destroy($req, new Response(), ['id' => '9']);
        $this->assertSame(200, $res->getStatusCode());
    }

    public function test_index_pasa_filtros_paginacion_y_search_al_repo(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->expects($this->once())
             ->method('listForTenant')
             ->with(self::TENANT, [
                 'q' => 'pizza',
                 'status' => 'active',
                 'sort' => 'redeemed_at',
                 'page' => 3,
                 'per_page' => 50,
             ])
             ->willReturn(['items' => [], 'total' => 0, 'page' => 3, 'per_page' => 50, 'total_pages' => 1, 'counts' => []]);

        $req = $this->withAuth(TestRequest::create('GET', '/api/giftcards'))
            ->withQueryParams([
                'q' => 'pizza', 'status' => 'active', 'sort' => 'redeemed_at',
                'page' => '3', 'per_page' => '50',
            ]);

        $this->controller($repo)->index($req, new Response());
    }

    public function test_qr_devuelve_png_binario(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->method('findForTenant')->willReturn([
            'id' => 9, 'token' => 'a1b2c3d4e5f6071829304a5b6c7d8e9f',
            'title' => 'X', 'status' => 'active',
        ]);

        $req = $this->withAuth(TestRequest::create('GET', '/api/giftcards/9/qr'));
        $res = $this->controller($repo)->qr($req, new Response(), ['id' => '9']);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('image/png', $res->getHeaderLine('Content-Type'));
        $body = (string) $res->getBody();
        $this->assertGreaterThan(100, strlen($body));
        $this->assertSame("\x89PNG\r\n\x1A\n", substr($body, 0, 8));
    }

    public function test_show_404_si_no_pertenece_al_tenant(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->method('findForTenant')->willReturn(null);

        $req = $this->withAuth(TestRequest::create('GET', '/api/giftcards/77'));
        $res = $this->controller($repo)->show($req, new Response(), ['id' => '77']);
        $this->assertSame(404, $res->getStatusCode());
    }

    public function test_token_service_genera_unicos_y_da_short_label(): void
    {
        $svc = new TokenService();
        $token = $svc->unique(fn (string $t) => false);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $token);

        $short = TokenService::shortLabel('a1b2c3d4e5f6071829304a5b6c7d8e9f');
        $this->assertSame('A1B2C3D4', $short);
    }

    public function test_token_service_reintenta_si_hay_colision(): void
    {
        $svc = new TokenService();
        $calls = 0;
        $token = $svc->unique(function (string $t) use (&$calls): bool {
            $calls++;
            return $calls < 3;   // los primeros 2 dicen "ya existe"
        });
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $token);
        $this->assertSame(3, $calls);
    }

    public function test_store_rechaza_sender_email_con_formato_invalido(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->expects($this->never())->method('create');

        $req = $this->withAuth(TestRequest::create('POST', '/api/giftcards'))
            ->withParsedBody([
                'title'        => 'Combo',
                'sender_name'  => 'María',
                'sender_email' => 'no-es-email',
            ]);

        $res  = $this->controller($repo)->store($req, new Response());
        $body = json_decode((string) $res->getBody(), true);

        $this->assertSame(422, $res->getStatusCode());
        $this->assertArrayHasKey('sender_email', $body['fields']);
    }

    public function test_store_acepta_sender_name_sin_sender_email(): void
    {
        $repo = $this->createMock(GiftcardRepository::class);
        $repo->method('tokenExists')->willReturn(false);
        $repo->expects($this->once())
             ->method('create')
             ->with(
                 self::TENANT,
                 self::USER_ID,
                 $this->matchesRegularExpression('/^[a-f0-9]{32}$/'),
                 $this->callback(fn (array $d) => $d['sender_name'] === 'María' && $d['sender_email'] === null),
             )
             ->willReturn(123);
        $repo->method('findForTenant')->willReturn([
            'id' => 123, 'token' => 'a1b2c3d4e5f6071829304a5b6c7d8e9f',
            'title' => 'Combo', 'status' => 'active',
        ]);

        $req = $this->withAuth(TestRequest::create('POST', '/api/giftcards'))
            ->withParsedBody(['title' => 'Combo', 'sender_name' => 'María']);

        $res = $this->controller($repo)->store($req, new Response());
        $this->assertSame(201, $res->getStatusCode());
    }
}
