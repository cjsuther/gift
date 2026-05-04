<?php

declare(strict_types=1);

namespace App\Tests\Controllers;

use App\Auth\AuthenticatedUser;
use App\Controllers\ProfileController;
use App\Middleware\AuthMiddleware;
use App\Repositories\UserRepository;
use App\Tests\Support\TestRequest;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;

final class ProfileControllerTest extends TestCase
{
    private const USER_ID = 42;

    private function user(): AuthenticatedUser
    {
        return new AuthenticatedUser(self::USER_ID, 'establishment_admin', 5, 'yo@x.com', 'Yo');
    }

    private function withAuth(\Psr\Http\Message\ServerRequestInterface $req): \Psr\Http\Message\ServerRequestInterface
    {
        return $req->withAttribute(AuthMiddleware::REQUEST_ATTR, $this->user());
    }

    public function test_actualiza_solo_el_nombre(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->expects($this->once())
             ->method('updateAny')
             ->with(self::USER_ID, $this->callback(fn (array $p) => $p === ['name' => 'Yo Editado', 'email' => 'yo@x.com']));
        $repo->method('findAny')->willReturn(['id' => self::USER_ID, 'name' => 'Yo Editado', 'email' => 'yo@x.com']);

        $controller = new ProfileController($repo);
        $req  = $this->withAuth(TestRequest::create('PUT', '/api/perfil'))
            ->withParsedBody(['name' => 'Yo Editado', 'email' => 'yo@x.com']);
        $res  = $controller->update($req, new Response());

        $this->assertSame(200, $res->getStatusCode());
    }

    public function test_rechaza_email_repetido(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('emailExists')
             ->with('otro@x.com', self::USER_ID)
             ->willReturn(true);
        $repo->expects($this->never())->method('updateAny');

        $controller = new ProfileController($repo);
        $req = $this->withAuth(TestRequest::create('PUT', '/api/perfil'))
            ->withParsedBody(['email' => 'otro@x.com']);
        $res = $controller->update($req, new Response());

        $this->assertSame(422, $res->getStatusCode());
    }

    public function test_cambio_password_requiere_current_password(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->expects($this->never())->method('updateAny');
        $repo->expects($this->never())->method('getPasswordHash');

        $controller = new ProfileController($repo);
        $req = $this->withAuth(TestRequest::create('PUT', '/api/perfil'))
            ->withParsedBody([
                'new_password'         => 'nuevaPass123',
                'new_password_confirm' => 'nuevaPass123',
                // current_password ausente
            ]);
        $res = $controller->update($req, new Response());

        $this->assertSame(422, $res->getStatusCode());
        $body = json_decode((string) $res->getBody(), true);
        $this->assertArrayHasKey('current_password', $body['fields']);
    }

    public function test_cambio_password_falla_si_current_es_incorrecta(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('getPasswordHash')->willReturn(password_hash('passwordReal', PASSWORD_BCRYPT));
        $repo->expects($this->never())->method('updateAny');

        $controller = new ProfileController($repo);
        $req = $this->withAuth(TestRequest::create('PUT', '/api/perfil'))
            ->withParsedBody([
                'current_password'     => 'passwordEquivocada',
                'new_password'         => 'nuevaPass123',
                'new_password_confirm' => 'nuevaPass123',
            ]);
        $res = $controller->update($req, new Response());

        $this->assertSame(422, $res->getStatusCode());
        $body = json_decode((string) $res->getBody(), true);
        $this->assertSame('Tu contraseña actual no es correcta.', $body['error']);
    }

    public function test_cambio_password_falla_si_confirm_no_coincide(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->expects($this->never())->method('getPasswordHash');
        $repo->expects($this->never())->method('updateAny');

        $controller = new ProfileController($repo);
        $req = $this->withAuth(TestRequest::create('PUT', '/api/perfil'))
            ->withParsedBody([
                'current_password'     => 'passwordReal',
                'new_password'         => 'nuevaPass123',
                'new_password_confirm' => 'OTRA_DIFERENTE',
            ]);
        $res = $controller->update($req, new Response());

        $this->assertSame(422, $res->getStatusCode());
        $body = json_decode((string) $res->getBody(), true);
        $this->assertArrayHasKey('new_password_confirm', $body['fields']);
    }

    public function test_cambio_password_falla_si_es_corta(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->expects($this->never())->method('updateAny');

        $controller = new ProfileController($repo);
        $req = $this->withAuth(TestRequest::create('PUT', '/api/perfil'))
            ->withParsedBody([
                'current_password'     => 'passwordReal',
                'new_password'         => 'corta',
                'new_password_confirm' => 'corta',
            ]);
        $res = $controller->update($req, new Response());

        $this->assertSame(422, $res->getStatusCode());
    }

    public function test_cambio_password_exitoso_pasa_la_nueva_al_repo(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('getPasswordHash')->willReturn(password_hash('passwordReal', PASSWORD_BCRYPT));
        $repo->expects($this->once())
             ->method('updateAny')
             ->with(
                 self::USER_ID,
                 $this->callback(fn (array $p) => ($p['password'] ?? null) === 'nuevaPass123Segura'),
             );
        $repo->method('findAny')->willReturn(['id' => self::USER_ID, 'name' => 'Yo', 'email' => 'yo@x.com']);

        $controller = new ProfileController($repo);
        $req = $this->withAuth(TestRequest::create('PUT', '/api/perfil'))
            ->withParsedBody([
                'current_password'     => 'passwordReal',
                'new_password'         => 'nuevaPass123Segura',
                'new_password_confirm' => 'nuevaPass123Segura',
            ]);
        $res = $controller->update($req, new Response());

        $this->assertSame(200, $res->getStatusCode());
    }

    public function test_no_se_puede_cambiar_role_o_establishment_via_perfil(): void
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->expects($this->once())
             ->method('updateAny')
             ->with(
                 self::USER_ID,
                 $this->callback(function (array $p): bool {
                     return !array_key_exists('role', $p)
                         && !array_key_exists('establishment_id', $p);
                 }),
             );
        $repo->method('findAny')->willReturn(['id' => self::USER_ID]);

        $controller = new ProfileController($repo);
        $req = $this->withAuth(TestRequest::create('PUT', '/api/perfil'))
            ->withParsedBody([
                'name'             => 'Yo',
                'email'            => 'yo@x.com',
                'role'             => 'super_admin',   // intento de elevación
                'establishment_id' => 999,             // intento de moverse de tenant
            ]);
        $controller->update($req, new Response());
    }
}
