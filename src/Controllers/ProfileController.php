<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthenticatedUser;
use App\Helpers\Response as ApiResponse;
use App\Helpers\Validator;
use App\Middleware\AuthMiddleware;
use App\Repositories\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Permite a cualquier usuario logueado editar SU PROPIO perfil
 * (nombre, email y password). No requiere rol específico.
 *
 * Cambio de password: requiere current_password verificada con bcrypt
 * contra el hash en DB. Sin esto, alguien con acceso temporal a la
 * sesión podría cambiar la password y bloquear al dueño.
 */
final class ProfileController
{
    public function __construct(private readonly UserRepository $repo)
    {
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $user = $this->currentUser($request);
        $data = $this->parseBody($request);

        $v = new Validator($data);
        if (array_key_exists('name', $data)) {
            $v->minLength('name', 2);
        }
        if (array_key_exists('email', $data)) {
            $v->required('email')->email('email');
        }
        if ($v->fails()) {
            return ApiResponse::error($response, 'Datos inválidos.', 422, ['fields' => $v->errors()]);
        }

        // Verificar email único (si cambió)
        $newEmail = isset($data['email']) ? trim((string) $data['email']) : null;
        if ($newEmail !== null && $newEmail !== '' && $newEmail !== $user->email) {
            if ($this->repo->emailExists($newEmail, $user->id)) {
                return ApiResponse::error($response, 'Ese email ya está en uso por otro usuario.', 422, [
                    'fields' => ['email' => 'Email ya registrado.'],
                ]);
            }
        }

        // Cambio de password (opcional pero con verificación estricta)
        $patch = [];
        $wantsPasswordChange = !empty($data['new_password']);

        if ($wantsPasswordChange) {
            $newPwd     = (string) $data['new_password'];
            $confirmPwd = (string) ($data['new_password_confirm'] ?? '');
            $currentPwd = (string) ($data['current_password'] ?? '');

            if ($currentPwd === '') {
                return ApiResponse::error($response, 'Necesitás ingresar tu contraseña actual para cambiarla.', 422, [
                    'fields' => ['current_password' => 'Requerida para cambiar la contraseña.'],
                ]);
            }
            if (mb_strlen($newPwd) < 8) {
                return ApiResponse::error($response, 'La nueva contraseña debe tener al menos 8 caracteres.', 422, [
                    'fields' => ['new_password' => 'Mínimo 8 caracteres.'],
                ]);
            }
            if ($newPwd !== $confirmPwd) {
                return ApiResponse::error($response, 'La confirmación no coincide.', 422, [
                    'fields' => ['new_password_confirm' => 'No coincide con la nueva contraseña.'],
                ]);
            }

            $storedHash = $this->repo->getPasswordHash($user->id);
            if ($storedHash === null || !password_verify($currentPwd, $storedHash)) {
                return ApiResponse::error($response, 'Tu contraseña actual no es correcta.', 422, [
                    'fields' => ['current_password' => 'Incorrecta.'],
                ]);
            }

            $patch['password'] = $newPwd;
        }

        if (array_key_exists('name', $data)) {
            $patch['name'] = trim((string) $data['name']);
        }
        if ($newEmail !== null && $newEmail !== '') {
            $patch['email'] = $newEmail;
        }

        if ($patch === []) {
            return ApiResponse::json($response, ['user' => $this->repo->findAny($user->id), 'updated' => false]);
        }

        // Reusamos updateAny — el whitelist del repo ya excluye role/establishment_id.
        $this->repo->updateAny($user->id, $patch);

        return ApiResponse::json($response, [
            'user'     => $this->repo->findAny($user->id),
            'updated'  => true,
        ]);
    }

    private function currentUser(ServerRequestInterface $request): AuthenticatedUser
    {
        $u = $request->getAttribute(AuthMiddleware::REQUEST_ATTR);
        if (!$u instanceof AuthenticatedUser) {
            throw new \RuntimeException('Usuario no inyectado por AuthMiddleware.');
        }
        return $u;
    }

    private function parseBody(ServerRequestInterface $request): array
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed)) {
            return $parsed;
        }
        $raw = (string) $request->getBody();
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
