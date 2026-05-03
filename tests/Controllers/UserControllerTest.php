<?php

declare(strict_types=1);

namespace App\Tests\Controllers;

use App\Controllers\UserController;
use App\Middleware\TenantMiddleware;
use App\Repositories\UserRepository;
use App\Tests\Support\TestRequest;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;

final class UserControllerTest extends TestCase
{
    private const TENANT = 7;

    public function test_store_rechaza_422_si_faltan_campos(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->expects($this->never())->method('create');

        $controller = new UserController($repo);

        $req = $this->withTenant(TestRequest::create('POST', '/api/users'))
            ->withParsedBody(['name' => 'X']);  // faltan email, password

        $res  = $controller->store($req, new Response());
        $body = json_decode((string) $res->getBody(), true);

        $this->assertSame(422, $res->getStatusCode());
        $this->assertArrayHasKey('email', $body['fields']);
        $this->assertArrayHasKey('password', $body['fields']);
    }

    public function test_store_rechaza_si_el_email_ya_existe(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('emailExists')->willReturn(true);
        $repo->expects($this->never())->method('create');

        $controller = new UserController($repo);

        $req = $this->withTenant(TestRequest::create('POST', '/api/users'))
            ->withParsedBody([
                'name'     => 'Juan',
                'email'    => 'duplicado@x.com',
                'password' => 'unaPassDe8+',
            ]);

        $res = $controller->store($req, new Response());
        $this->assertSame(422, $res->getStatusCode());
    }

    public function test_store_fuerza_el_establishment_id_del_tenant_e_ignora_el_del_body(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('emailExists')->willReturn(false);

        // CRITICAL: aunque el body manda establishment_id=999, el repo recibe el del tenant.
        $repo->expects($this->once())
             ->method('create')
             ->with(
                 self::TENANT,                        // tenant del request, NO 999
                 $this->callback(fn (array $d) => $d['name'] === 'Juan' && $d['email'] === 'juan@x.com'),
             )
             ->willReturn(123);

        $repo->method('findForTenant')->with(123, self::TENANT)->willReturn([
            'id' => 123, 'role' => 'establishment_user', 'establishment_id' => self::TENANT,
            'name' => 'Juan', 'email' => 'juan@x.com', 'is_active' => 1,
        ]);

        $controller = new UserController($repo);

        $req = $this->withTenant(TestRequest::create('POST', '/api/users'))
            ->withParsedBody([
                'name'             => 'Juan',
                'email'            => 'juan@x.com',
                'password'         => 'unaPassDe8+',
                'establishment_id' => 999,           // intento de inyección
                'role'             => 'super_admin', // intento de elevación
            ]);

        $res = $controller->store($req, new Response());
        $this->assertSame(201, $res->getStatusCode());
    }

    public function test_index_pasa_el_tenant_y_el_search_al_repo(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->expects($this->once())
             ->method('listForTenant')
             ->with(self::TENANT, 'juan')
             ->willReturn([]);

        $controller = new UserController($repo);

        $req = $this->withTenant(TestRequest::create('GET', '/api/users'))
            ->withQueryParams(['q' => 'juan']);
        $controller->index($req, new Response());
    }

    public function test_update_404_si_el_usuario_no_pertenece_al_tenant(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findForTenant')->willReturn(null);  // no existe para este tenant
        $repo->expects($this->never())->method('update');

        $controller = new UserController($repo);

        $req = $this->withTenant(TestRequest::create('PUT', '/api/users/55'))
            ->withParsedBody(['name' => 'Otro']);

        $res = $controller->update($req, new Response(), ['id' => '55']);
        $this->assertSame(404, $res->getStatusCode());
    }

    public function test_destroy_desactiva_y_devuelve_ok(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findForTenant')->willReturn([
            'id' => 9, 'role' => 'establishment_user', 'establishment_id' => self::TENANT,
            'name' => 'X', 'email' => 'x@y.com', 'is_active' => 1,
        ]);
        $repo->expects($this->once())->method('deactivate')->with(9, self::TENANT);

        $controller = new UserController($repo);

        $req  = $this->withTenant(TestRequest::create('DELETE', '/api/users/9'));
        $res  = $controller->destroy($req, new Response(), ['id' => '9']);
        $body = json_decode((string) $res->getBody(), true);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertTrue($body['ok']);
    }

    private function withTenant(\Psr\Http\Message\ServerRequestInterface $req): \Psr\Http\Message\ServerRequestInterface
    {
        return $req->withAttribute(TenantMiddleware::REQUEST_ATTR, self::TENANT);
    }
}
