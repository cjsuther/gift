<?php

declare(strict_types=1);

namespace App\Tests\Middleware;

use App\Auth\AuthenticatedUser;
use App\Middleware\AuthMiddleware;
use App\Middleware\TenantMiddleware;
use App\Tests\Support\PassthroughHandler;
use App\Tests\Support\TestRequest;
use PHPUnit\Framework\TestCase;

final class TenantMiddlewareTest extends TestCase
{
    private TenantMiddleware $mw;
    private PassthroughHandler $next;

    protected function setUp(): void
    {
        $this->mw   = new TenantMiddleware();
        $this->next = new PassthroughHandler();
    }

    public function test_responde_401_si_no_hay_usuario_inyectado(): void
    {
        $req = TestRequest::create();
        $res = $this->mw->process($req, $this->next);

        $this->assertSame(401, $res->getStatusCode());
        $this->assertFalse($this->next->wasCalled);
    }

    public function test_rechaza_super_admin_con_403(): void
    {
        $user = new AuthenticatedUser(1, 'super_admin', null, 's@x.com', 'S');
        $req  = TestRequest::create()->withAttribute(AuthMiddleware::REQUEST_ATTR, $user);

        $res = $this->mw->process($req, $this->next);

        $this->assertSame(403, $res->getStatusCode(), 'super_admin no debería operar sobre recursos de un establecimiento');
        $this->assertFalse($this->next->wasCalled);
    }

    public function test_inyecta_establishment_id_para_admin_de_estab(): void
    {
        $user = new AuthenticatedUser(1, 'establishment_admin', 42, 'a@x.com', 'A');
        $req  = TestRequest::create()->withAttribute(AuthMiddleware::REQUEST_ATTR, $user);

        $res = $this->mw->process($req, $this->next);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertTrue($this->next->wasCalled);
        $this->assertSame(42, $this->next->lastRequest?->getAttribute(TenantMiddleware::REQUEST_ATTR));
    }

    public function test_inyecta_establishment_id_para_user_de_estab(): void
    {
        $user = new AuthenticatedUser(2, 'establishment_user', 7, 'u@x.com', 'U');
        $req  = TestRequest::create()->withAttribute(AuthMiddleware::REQUEST_ATTR, $user);

        $res = $this->mw->process($req, $this->next);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertTrue($this->next->wasCalled);
        $this->assertSame(7, $this->next->lastRequest?->getAttribute(TenantMiddleware::REQUEST_ATTR));
    }

    public function test_owns_resource_es_true_si_coincide_el_tenant(): void
    {
        $req = TestRequest::create()->withAttribute(TenantMiddleware::REQUEST_ATTR, 5);
        $this->assertTrue(TenantMiddleware::ownsResource($req, 5));
    }

    public function test_owns_resource_es_false_si_no_coincide_el_tenant(): void
    {
        $req = TestRequest::create()->withAttribute(TenantMiddleware::REQUEST_ATTR, 5);
        $this->assertFalse(TenantMiddleware::ownsResource($req, 9));
    }

    public function test_owns_resource_es_false_si_resource_es_null(): void
    {
        $req = TestRequest::create()->withAttribute(TenantMiddleware::REQUEST_ATTR, 5);
        $this->assertFalse(TenantMiddleware::ownsResource($req, null));
    }

    public function test_owns_resource_es_false_si_no_hay_tenant_en_el_request(): void
    {
        // Defensivo: si alguien usa ownsResource sin haber pasado por TenantMiddleware
        $req = TestRequest::create();
        $this->assertFalse(TenantMiddleware::ownsResource($req, 5));
    }
}
