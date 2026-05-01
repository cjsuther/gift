<?php

declare(strict_types=1);

namespace App\Tests\Middleware;

use App\Auth\AuthenticatedUser;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Tests\Support\PassthroughHandler;
use App\Tests\Support\TestRequest;
use PHPUnit\Framework\TestCase;

final class RoleMiddlewareTest extends TestCase
{
    private PassthroughHandler $next;

    protected function setUp(): void
    {
        $this->next = new PassthroughHandler();
    }

    public function test_constructor_rechaza_lista_vacia(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RoleMiddleware();
    }

    public function test_constructor_rechaza_rol_desconocido(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RoleMiddleware('owner');
    }

    public function test_responde_401_si_no_hay_usuario_inyectado(): void
    {
        $mw  = new RoleMiddleware('super_admin');
        $req = TestRequest::create();
        $res = $mw->process($req, $this->next);

        $this->assertSame(401, $res->getStatusCode());
        $this->assertFalse($this->next->wasCalled);
    }

    public function test_responde_403_si_el_rol_no_esta_en_la_whitelist(): void
    {
        $user = new AuthenticatedUser(1, 'establishment_user', 5, 'u@x.com', 'U');
        $req  = TestRequest::create()->withAttribute(AuthMiddleware::REQUEST_ATTR, $user);

        $mw  = new RoleMiddleware('establishment_admin');
        $res = $mw->process($req, $this->next);

        $this->assertSame(403, $res->getStatusCode());
        $this->assertFalse($this->next->wasCalled);
    }

    public function test_permite_paso_si_el_rol_esta_en_la_whitelist(): void
    {
        $user = new AuthenticatedUser(1, 'establishment_admin', 5, 'a@x.com', 'A');
        $req  = TestRequest::create()->withAttribute(AuthMiddleware::REQUEST_ATTR, $user);

        $mw  = new RoleMiddleware('establishment_admin', 'establishment_user');
        $res = $mw->process($req, $this->next);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertTrue($this->next->wasCalled);
    }

    public function test_no_aplica_jerarquia_super_admin_no_pasa_si_no_esta_listado(): void
    {
        // Defensivo: super_admin NO entra en endpoints de tenant aunque "sea más"
        $user = new AuthenticatedUser(1, 'super_admin', null, 's@x.com', 'S');
        $req  = TestRequest::create()->withAttribute(AuthMiddleware::REQUEST_ATTR, $user);

        $mw  = new RoleMiddleware('establishment_admin');
        $res = $mw->process($req, $this->next);

        $this->assertSame(403, $res->getStatusCode());
        $this->assertFalse($this->next->wasCalled);
    }

    public function test_establishment_user_no_puede_pasar_si_solo_admins(): void
    {
        $user = new AuthenticatedUser(1, 'establishment_user', 5, 'u@x.com', 'U');
        $req  = TestRequest::create()->withAttribute(AuthMiddleware::REQUEST_ATTR, $user);

        // Caso clave del spec: POST /api/giftcards solo admin
        $mw  = new RoleMiddleware('establishment_admin');
        $res = $mw->process($req, $this->next);

        $this->assertSame(403, $res->getStatusCode(), 'establishment_user no debería poder crear giftcards');
        $this->assertFalse($this->next->wasCalled);
    }

    public function test_establishment_user_si_puede_pasar_en_endpoints_de_canje(): void
    {
        $user = new AuthenticatedUser(1, 'establishment_user', 5, 'u@x.com', 'U');
        $req  = TestRequest::create()->withAttribute(AuthMiddleware::REQUEST_ATTR, $user);

        // POST /api/redeem/{token}: admin o user
        $mw  = new RoleMiddleware('establishment_admin', 'establishment_user');
        $res = $mw->process($req, $this->next);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertTrue($this->next->wasCalled);
    }
}
