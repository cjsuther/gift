<?php

declare(strict_types=1);

namespace App\Tests\Controllers;

use App\Controllers\EstablishmentController;
use App\Repositories\EstablishmentRepository;
use App\Tests\Support\TestRequest;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;

final class EstablishmentControllerTest extends TestCase
{
    public function test_store_rechaza_con_422_si_faltan_campos(): void
    {
        $repo = $this->createMock(EstablishmentRepository::class);
        $repo->expects($this->never())->method('createWithAdmin');

        $controller = new EstablishmentController($repo, null);

        $request = TestRequest::create('POST', '/api/admin/establishments')
            ->withParsedBody(['name' => 'X']);  // demasiado corto + faltan admin_*

        $res    = $controller->store($request, new Response());
        $body   = json_decode((string) $res->getBody(), true);

        $this->assertSame(422, $res->getStatusCode());
        $this->assertSame('Datos inválidos.', $body['error']);
        $this->assertArrayHasKey('fields', $body);
        $this->assertArrayHasKey('name', $body['fields']);
        $this->assertArrayHasKey('admin_email', $body['fields']);
    }

    public function test_store_rechaza_si_el_email_del_admin_ya_existe(): void
    {
        $repo = $this->createMock(EstablishmentRepository::class);
        $repo->method('emailExists')->willReturn(true);
        $repo->expects($this->never())->method('createWithAdmin');

        $controller = new EstablishmentController($repo, null);

        $request = TestRequest::create('POST', '/api/admin/establishments')
            ->withParsedBody([
                'name'           => 'Mi Estab',
                'admin_name'     => 'Juan',
                'admin_email'    => 'duplicado@x.com',
                'admin_password' => 'unaPassDe8+',
            ]);

        $res  = $controller->store($request, new Response());
        $body = json_decode((string) $res->getBody(), true);

        $this->assertSame(422, $res->getStatusCode());
        $this->assertStringContainsString('email', strtolower($body['error']));
    }

    public function test_store_crea_establishment_y_admin_y_devuelve_201(): void
    {
        $repo = $this->createMock(EstablishmentRepository::class);

        $repo->method('emailExists')->willReturn(false);
        $repo->method('slugExists')->willReturn(false);

        $repo->expects($this->once())
             ->method('createWithAdmin')
             ->with(
                 $this->callback(function (array $data): bool {
                     return $data['name'] === 'Mi Estab'
                         && $data['slug'] === 'mi-estab'
                         && $data['primary_color'] === '#e63946';
                 }),
                 $this->callback(function (array $admin): bool {
                     return $admin['email'] === 'admin@x.com'
                         && $admin['name'] === 'Juan';
                 }),
                 $this->isNull(),
             )
             ->willReturn(['establishment_id' => 42, 'admin_user_id' => 7]);

        $repo->method('find')->with(42)->willReturn([
            'id' => 42, 'name' => 'Mi Estab', 'slug' => 'mi-estab',
            'primary_color' => '#e63946', 'is_active' => 1,
        ]);

        $controller = new EstablishmentController($repo, null);

        $request = TestRequest::create('POST', '/api/admin/establishments')
            ->withParsedBody([
                'name'           => 'Mi Estab',
                'primary_color'  => '#E63946',
                'admin_name'     => 'Juan',
                'admin_email'    => 'admin@x.com',
                'admin_password' => 'unaPassDe8+',
            ]);

        $res  = $controller->store($request, new Response());
        $body = json_decode((string) $res->getBody(), true);

        $this->assertSame(201, $res->getStatusCode());
        $this->assertSame(42, $body['establishment']['id']);
        $this->assertSame(7, $body['admin_user_id']);
    }

    public function test_index_devuelve_la_lista_del_repo(): void
    {
        $repo = $this->createMock(EstablishmentRepository::class);
        $repo->method('list')->with(null)->willReturn([
            ['id' => 1, 'name' => 'Uno', 'slug' => 'uno', 'is_active' => 1],
            ['id' => 2, 'name' => 'Dos', 'slug' => 'dos', 'is_active' => 0],
        ]);

        $controller = new EstablishmentController($repo, null);

        $res  = $controller->index(TestRequest::create('GET', '/api/admin/establishments'), new Response());
        $body = json_decode((string) $res->getBody(), true);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertCount(2, $body['establishments']);
        $this->assertSame('Uno', $body['establishments'][0]['name']);
    }

    public function test_index_pasa_el_query_search_al_repo(): void
    {
        $repo = $this->createMock(EstablishmentRepository::class);
        $repo->expects($this->once())
             ->method('list')
             ->with('rusvel')
             ->willReturn([]);

        $controller = new EstablishmentController($repo, null);

        $req = TestRequest::create('GET', '/api/admin/establishments')
            ->withQueryParams(['q' => 'rusvel']);
        $controller->index($req, new Response());
    }

    public function test_destroy_404_si_no_existe(): void
    {
        $repo = $this->createMock(EstablishmentRepository::class);
        $repo->method('find')->willReturn(null);
        $repo->expects($this->never())->method('deactivate');

        $controller = new EstablishmentController($repo, null);

        $res = $controller->destroy(TestRequest::create('DELETE', '/api/admin/establishments/999'), new Response(), ['id' => '999']);
        $this->assertSame(404, $res->getStatusCode());
    }

    public function test_destroy_desactiva_y_devuelve_ok(): void
    {
        $repo = $this->createMock(EstablishmentRepository::class);
        $repo->method('find')->willReturn(['id' => 5, 'name' => 'X', 'is_active' => 1]);
        $repo->expects($this->once())->method('deactivate')->with(5);

        $controller = new EstablishmentController($repo, null);

        $res  = $controller->destroy(TestRequest::create('DELETE', '/api/admin/establishments/5'), new Response(), ['id' => '5']);
        $body = json_decode((string) $res->getBody(), true);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertTrue($body['ok']);
    }
}
