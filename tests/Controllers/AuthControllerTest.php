<?php

declare(strict_types=1);

namespace App\Tests\Controllers;

use App\Auth\JwtService;
use App\Auth\PdoUserRepository;
use App\Controllers\AuthController;
use App\Tests\Support\TestRequest;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;

final class AuthControllerTest extends TestCase
{
    private const SECRET     = 'phpunit-secret-32chars-XXXXXXXXXX';
    private const REMEMBER_H = 720;

    private function userRow(): array
    {
        return [
            'id'                => 1,
            'role'              => 'establishment_admin',
            'establishment_id'  => 5,
            'email'             => 'a@x.com',
            'name'              => 'Admin',
            'password_hash'     => password_hash('correctPassword', PASSWORD_BCRYPT),
            'is_active'         => 1,
        ];
    }

    private function buildController(?array $userRow = null): AuthController
    {
        $repo = $this->createMock(PdoUserRepository::class);
        $repo->method('findActiveByEmail')->willReturn($userRow);
        $repo->method('hydrate')->willReturnCallback(fn (array $r) => new \App\Auth\AuthenticatedUser(
            id: (int) $r['id'],
            role: (string) $r['role'],
            establishmentId: $r['establishment_id'] !== null ? (int) $r['establishment_id'] : null,
            email: (string) $r['email'],
            name: (string) $r['name'],
        ));

        return new AuthController($repo, new JwtService(self::SECRET, 2), self::REMEMBER_H);
    }

    public function test_login_422_si_faltan_campos(): void
    {
        $controller = $this->buildController();
        $req = TestRequest::create('POST', '/api/auth/login')->withParsedBody([]);
        $res = $controller->login($req, new Response());
        $this->assertSame(422, $res->getStatusCode());
    }

    public function test_login_401_si_credenciales_incorrectas(): void
    {
        $controller = $this->buildController($this->userRow());
        $req = TestRequest::create('POST', '/api/auth/login')->withParsedBody([
            'email' => 'a@x.com', 'password' => 'wrongPassword',
        ]);
        $res = $controller->login($req, new Response());
        $this->assertSame(401, $res->getStatusCode());
        // No debe haber Set-Cookie en login fallido
        $this->assertSame('', $res->getHeaderLine('Set-Cookie'));
    }

    public function test_login_remember_true_setea_cookie_con_max_age(): void
    {
        $controller = $this->buildController($this->userRow());
        $req = TestRequest::create('POST', '/api/auth/login')->withParsedBody([
            'email' => 'a@x.com', 'password' => 'correctPassword', 'remember' => true,
        ]);
        $res = $controller->login($req, new Response());

        $this->assertSame(200, $res->getStatusCode());
        $cookie = $res->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('auth_token=', $cookie);
        $this->assertStringContainsString('HttpOnly', $cookie);
        $this->assertStringContainsString('Secure', $cookie);
        $this->assertStringContainsString('SameSite=Lax', $cookie);
        $this->assertStringContainsString('Max-Age=' . (self::REMEMBER_H * 3600), $cookie);

        $body = json_decode((string) $res->getBody(), true);
        $this->assertTrue($body['remember']);
        $this->assertNotEmpty($body['token']);
    }

    public function test_login_remember_false_setea_cookie_sin_max_age_session_cookie(): void
    {
        $controller = $this->buildController($this->userRow());
        $req = TestRequest::create('POST', '/api/auth/login')->withParsedBody([
            'email' => 'a@x.com', 'password' => 'correctPassword', 'remember' => false,
        ]);
        $res = $controller->login($req, new Response());

        $this->assertSame(200, $res->getStatusCode());
        $cookie = $res->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('auth_token=', $cookie);
        $this->assertStringContainsString('HttpOnly', $cookie);
        $this->assertStringNotContainsString('Max-Age=', $cookie);   // session cookie
    }

    public function test_login_default_es_remember_true(): void
    {
        $controller = $this->buildController($this->userRow());
        $req = TestRequest::create('POST', '/api/auth/login')->withParsedBody([
            'email' => 'a@x.com', 'password' => 'correctPassword',
            // remember NO especificado
        ]);
        $res = $controller->login($req, new Response());

        $this->assertSame(200, $res->getStatusCode());
        $cookie = $res->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('Max-Age=', $cookie);
    }

    public function test_logout_borra_la_cookie_con_max_age_0(): void
    {
        $controller = $this->buildController();
        $res = $controller->logout(TestRequest::create('POST', '/api/auth/logout'), new Response());

        $this->assertSame(200, $res->getStatusCode());
        $cookie = $res->getHeaderLine('Set-Cookie');
        $this->assertStringContainsString('auth_token=;', $cookie);
        $this->assertStringContainsString('Max-Age=0', $cookie);
    }
}
