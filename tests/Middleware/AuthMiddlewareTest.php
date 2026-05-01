<?php

declare(strict_types=1);

namespace App\Tests\Middleware;

use App\Auth\AuthenticatedUser;
use App\Auth\JwtService;
use App\Middleware\AuthMiddleware;
use App\Tests\Support\InMemoryUserProvider;
use App\Tests\Support\PassthroughHandler;
use App\Tests\Support\TestRequest;
use PHPUnit\Framework\TestCase;

final class AuthMiddlewareTest extends TestCase
{
    private const SECRET = 'phpunit-secret-32chars-XXXXXXXXXX';

    private JwtService $jwt;
    private InMemoryUserProvider $users;
    private AuthMiddleware $mw;
    private PassthroughHandler $next;

    protected function setUp(): void
    {
        $this->jwt   = new JwtService(self::SECRET, 2);
        $this->users = new InMemoryUserProvider();
        $this->mw    = new AuthMiddleware($this->jwt, $this->users);
        $this->next  = new PassthroughHandler();
    }

    public function test_responde_401_sin_header_authorization(): void
    {
        $req = TestRequest::create();
        $res = $this->mw->process($req, $this->next);

        $this->assertSame(401, $res->getStatusCode());
        $this->assertFalse($this->next->wasCalled);
    }

    public function test_responde_401_con_token_invalido(): void
    {
        $req = TestRequest::create('GET', '/', ['Authorization' => 'Bearer not-a-real-jwt']);
        $res = $this->mw->process($req, $this->next);

        $this->assertSame(401, $res->getStatusCode());
        $this->assertFalse($this->next->wasCalled);
    }

    public function test_responde_401_si_el_usuario_no_existe_o_esta_inactivo(): void
    {
        $token = $this->jwt->issue(99, 'establishment_admin', 1);
        $req   = TestRequest::create('GET', '/', ['Authorization' => "Bearer {$token}"]);
        $res   = $this->mw->process($req, $this->next);

        $this->assertSame(401, $res->getStatusCode());
        $this->assertFalse($this->next->wasCalled);
    }

    public function test_responde_401_si_el_rol_del_token_no_coincide_con_el_de_la_db(): void
    {
        // Token dice admin, pero en DB el usuario es user (le bajaron el rol).
        $this->users->add(new AuthenticatedUser(7, 'establishment_user', 3, 'a@b.c', 'A'));
        $token = $this->jwt->issue(7, 'establishment_admin', 3);
        $req   = TestRequest::create('GET', '/', ['Authorization' => "Bearer {$token}"]);
        $res   = $this->mw->process($req, $this->next);

        $this->assertSame(401, $res->getStatusCode());
        $this->assertFalse($this->next->wasCalled);
    }

    public function test_inyecta_usuario_en_el_request_y_continua(): void
    {
        $user = new AuthenticatedUser(1, 'establishment_admin', 5, 'admin@x.com', 'Admin');
        $this->users->add($user);

        $token = $this->jwt->issue(1, 'establishment_admin', 5);
        $req   = TestRequest::create('GET', '/', ['Authorization' => "Bearer {$token}"]);
        $res   = $this->mw->process($req, $this->next);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertTrue($this->next->wasCalled);
        $injected = $this->next->lastRequest?->getAttribute(AuthMiddleware::REQUEST_ATTR);
        $this->assertInstanceOf(AuthenticatedUser::class, $injected);
        $this->assertSame(1, $injected->id);
        $this->assertSame(5, $injected->establishmentId);
    }

    public function test_acepta_token_via_cookie(): void
    {
        $user = new AuthenticatedUser(2, 'super_admin', null, 'sa@x.com', 'SA');
        $this->users->add($user);

        $token = $this->jwt->issue(2, 'super_admin', null);
        $req   = TestRequest::create('GET', '/', [], ['auth_token' => $token]);
        $res   = $this->mw->process($req, $this->next);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertTrue($this->next->wasCalled);
    }

    public function test_responde_401_si_el_token_expiro(): void
    {
        $shortLivedJwt = new JwtService(self::SECRET, 0);
        $token = $shortLivedJwt->issue(1, 'super_admin', null);
        // Forzamos que ya pasó el "exp"
        sleep(1);

        $req = TestRequest::create('GET', '/', ['Authorization' => "Bearer {$token}"]);
        $res = $this->mw->process($req, $this->next);

        $this->assertSame(401, $res->getStatusCode());
    }
}
