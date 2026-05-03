<?php

declare(strict_types=1);

namespace App\Tests\Controllers;

use App\Auth\AuthenticatedUser;
use App\Controllers\AdminUserController;
use App\Middleware\AuthMiddleware;
use App\Repositories\UserRepository;
use App\Tests\Support\TestRequest;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;

final class AdminUserControllerTest extends TestCase
{
    private function superAdmin(int $id = 1): AuthenticatedUser
    {
        return new AuthenticatedUser($id, 'super_admin', null, "sa{$id}@x.com", "SA {$id}");
    }

    public function test_index_pasa_filtros_q_role_estab_active_al_repo(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->expects($this->once())
             ->method('listAll')
             ->with([
                 'q' => 'pedro',
                 'role' => 'establishment_user',
                 'establishment_id' => 5,
                 'is_active' => 1,
             ])
             ->willReturn([]);

        $controller = new AdminUserController($repo);

        $req = TestRequest::create('GET', '/api/admin/users')
            ->withAttribute(AuthMiddleware::REQUEST_ATTR, $this->superAdmin())
            ->withQueryParams([
                'q' => 'pedro',
                'role' => 'establishment_user',
                'establishment_id' => '5',
                'is_active' => '1',
            ]);
        $controller->index($req, new Response());
    }

    public function test_update_404_si_no_existe(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findAny')->willReturn(null);
        $repo->expects($this->never())->method('updateAny');

        $controller = new AdminUserController($repo);

        $req = TestRequest::create('PUT', '/api/admin/users/99')
            ->withAttribute(AuthMiddleware::REQUEST_ATTR, $this->superAdmin())
            ->withParsedBody(['name' => 'Nuevo']);

        $res = $controller->update($req, new Response(), ['id' => '99']);
        $this->assertSame(404, $res->getStatusCode());
    }

    public function test_update_403_si_intenta_editar_otro_super_admin(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findAny')->willReturn([
            'id' => 2, 'role' => 'super_admin', 'establishment_id' => null,
            'name' => 'Otro SA', 'email' => 'otro@sa.com', 'is_active' => 1,
        ]);
        $repo->expects($this->never())->method('updateAny');

        $controller = new AdminUserController($repo);

        $req = TestRequest::create('PUT', '/api/admin/users/2')
            ->withAttribute(AuthMiddleware::REQUEST_ATTR, $this->superAdmin(1))
            ->withParsedBody(['name' => 'Hack']);

        $res = $controller->update($req, new Response(), ['id' => '2']);
        $this->assertSame(403, $res->getStatusCode());
    }

    public function test_update_se_puede_a_si_mismo(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findAny')->willReturn([
            'id' => 1, 'role' => 'super_admin', 'establishment_id' => null,
            'name' => 'Yo', 'email' => 'yo@x.com', 'is_active' => 1,
        ]);
        $repo->expects($this->once())
             ->method('updateAny')
             ->with(1, $this->callback(fn (array $p) => ($p['name'] ?? null) === 'Yo Editado'));

        $controller = new AdminUserController($repo);

        $req = TestRequest::create('PUT', '/api/admin/users/1')
            ->withAttribute(AuthMiddleware::REQUEST_ATTR, $this->superAdmin(1))
            ->withParsedBody(['name' => 'Yo Editado']);

        $res = $controller->update($req, new Response(), ['id' => '1']);
        $this->assertSame(200, $res->getStatusCode());
    }

    public function test_update_bloquea_auto_desactivacion(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findAny')->willReturn([
            'id' => 1, 'role' => 'super_admin', 'establishment_id' => null,
            'name' => 'Yo', 'email' => 'yo@x.com', 'is_active' => 1,
        ]);
        $repo->expects($this->never())->method('updateAny');

        $controller = new AdminUserController($repo);

        $req = TestRequest::create('PUT', '/api/admin/users/1')
            ->withAttribute(AuthMiddleware::REQUEST_ATTR, $this->superAdmin(1))
            ->withParsedBody(['is_active' => 0]);

        $res  = $controller->update($req, new Response(), ['id' => '1']);
        $body = json_decode((string) $res->getBody(), true);
        $this->assertSame(422, $res->getStatusCode());
        $this->assertStringContainsString('vos mismo', $body['error']);
    }

    public function test_update_ignora_role_y_establishment_id_del_body(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findAny')->willReturn([
            'id' => 5, 'role' => 'establishment_user', 'establishment_id' => 10,
            'name' => 'X', 'email' => 'x@y.com', 'is_active' => 1,
        ]);
        // Verificamos que el patch enviado al repo SOLO tenga name/email/is_active/password,
        // nada de role o establishment_id.
        $repo->expects($this->once())
             ->method('updateAny')
             ->with(5, $this->callback(function (array $patch): bool {
                 return !array_key_exists('role', $patch)
                     && !array_key_exists('establishment_id', $patch);
             }));

        $controller = new AdminUserController($repo);

        $req = TestRequest::create('PUT', '/api/admin/users/5')
            ->withAttribute(AuthMiddleware::REQUEST_ATTR, $this->superAdmin())
            ->withParsedBody([
                'name'             => 'Editado',
                'role'             => 'super_admin',  // intento de elevación
                'establishment_id' => 999,            // intento de mover de tenant
            ]);

        $res = $controller->update($req, new Response(), ['id' => '5']);
        $this->assertSame(200, $res->getStatusCode());
    }

    public function test_destroy_bloquea_auto_desactivacion(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findAny')->willReturn([
            'id' => 1, 'role' => 'super_admin', 'establishment_id' => null,
            'name' => 'Yo', 'email' => 'yo@x.com', 'is_active' => 1,
        ]);
        $repo->expects($this->never())->method('deactivateAny');

        $controller = new AdminUserController($repo);

        $req = TestRequest::create('DELETE', '/api/admin/users/1')
            ->withAttribute(AuthMiddleware::REQUEST_ATTR, $this->superAdmin(1));

        $res = $controller->destroy($req, new Response(), ['id' => '1']);
        $this->assertSame(422, $res->getStatusCode());
    }

    public function test_destroy_bloquea_otro_super_admin(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findAny')->willReturn([
            'id' => 2, 'role' => 'super_admin', 'establishment_id' => null,
            'name' => 'Otro', 'email' => 'otro@sa.com', 'is_active' => 1,
        ]);
        $repo->expects($this->never())->method('deactivateAny');

        $controller = new AdminUserController($repo);

        $req = TestRequest::create('DELETE', '/api/admin/users/2')
            ->withAttribute(AuthMiddleware::REQUEST_ATTR, $this->superAdmin(1));

        $res = $controller->destroy($req, new Response(), ['id' => '2']);
        $this->assertSame(403, $res->getStatusCode());
    }

    public function test_destroy_desactiva_a_un_establishment_user(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findAny')->willReturn([
            'id' => 9, 'role' => 'establishment_user', 'establishment_id' => 3,
            'name' => 'Pedro', 'email' => 'pedro@x.com', 'is_active' => 1,
        ]);
        $repo->expects($this->once())->method('deactivateAny')->with(9);

        $controller = new AdminUserController($repo);

        $req  = TestRequest::create('DELETE', '/api/admin/users/9')
            ->withAttribute(AuthMiddleware::REQUEST_ATTR, $this->superAdmin(1));
        $res  = $controller->destroy($req, new Response(), ['id' => '9']);
        $body = json_decode((string) $res->getBody(), true);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertTrue($body['ok']);
    }
}
