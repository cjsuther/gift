<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthenticatedUser;
use App\Helpers\Response as ApiResponse;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Middleware\TenantMiddleware;
use App\Repositories\GiftcardRepository;
use App\Services\MailService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Flujo de canje. Las vistas usan AuthMiddleware con redirect a /login + ?next=...
 * para que después del login el QR-scan-flow vuelva a /redeem/{token}.
 *
 * Decisión: NO leakeamos si un token existe en otro establecimiento — devolvemos
 * el mismo "no encontrada" que si el token directamente no existiera. Quien tenga
 * acceso al QR de otro establecimiento se entera solo de que no es para él.
 */
final class RedemptionController
{
    public function __construct(
        private readonly GiftcardRepository $repo,
        private readonly ?MailService $mail = null,
        private readonly ?View $view = null,
        private readonly string $appUrl = '',
    ) {
    }

    /**
     * POST /api/redeem/{token} — marca como canjeada (idempotente vía CHECK del status en SQL).
     */
    public function redeem(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenant = $this->tenant($request);
        $user   = $this->currentUser($request);
        $token  = (string) ($args['token'] ?? '');

        if (preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            return ApiResponse::error($response, 'Token inválido.', 400);
        }

        $row = $this->repo->findByTokenForTenant($token, $tenant);
        if ($row === null) {
            return ApiResponse::notFound($response, 'Giftcard no encontrada.');
        }

        if ($row['status'] === 'redeemed') {
            return ApiResponse::error(
                $response,
                'Esta giftcard ya fue canjeada el ' . $row['redeemed_at'] . '.',
                422,
                ['status' => 'redeemed', 'giftcard' => $row]
            );
        }
        if ($row['status'] === 'cancelled') {
            return ApiResponse::error($response, 'Esta giftcard fue cancelada.', 422, ['status' => 'cancelled']);
        }
        if ($row['status'] === 'expired' || $this->isExpired($row['expires_at'] ?? null)) {
            return ApiResponse::error($response, 'Esta giftcard está vencida.', 422, ['status' => 'expired']);
        }

        $ok = $this->repo->redeem(
            (int) $row['id'],
            $tenant,
            $user->id,
            $this->ipFrom($request),
            $request->getHeaderLine('User-Agent') ?: null,
        );

        if (!$ok) {
            // Race condition: otro request la canjeó entre el find y el update
            $latest = $this->repo->findByTokenForTenant($token, $tenant);
            return ApiResponse::error(
                $response,
                'No se pudo canjear (otra acción la modificó). Refrescá la página.',
                409,
                ['giftcard' => $latest]
            );
        }

        $latest = $this->repo->findByTokenForTenant($token, $tenant);

        // Notificar al sender por email (best-effort, no rompe el canje si falla)
        $this->notifySenderIfPossible($latest);

        return ApiResponse::json($response, [
            'ok'       => true,
            'giftcard' => $latest,
        ]);
    }

    /**
     * Si el sender_email está cargado y el SMTP configurado, envía el email
     * de notificación. Cualquier error se loguea pero no afecta al canje.
     */
    private function notifySenderIfPossible(?array $giftcard): void
    {
        if ($giftcard === null || empty($giftcard['sender_email'])) {
            return;
        }
        if ($this->mail === null || !$this->mail->isConfigured() || $this->view === null) {
            return;
        }

        // Necesitamos también datos del establecimiento (nombre, color) para el email.
        // findByTokenForTenant no los devuelve; usamos findByTokenAny que sí.
        $full = $this->repo->findByTokenAny((string) $giftcard['token']);
        if ($full === null) {
            return;
        }

        try {
            $html = $this->view->renderToString('emails/redemption_notification', [
                'giftcard' => $full,
                'appUrl'   => $this->appUrl,
            ]);

            $recipientLabel = !empty($full['recipient_name']) ? $full['recipient_name'] : 'tu destinatario';
            $subject = "✓ Tu giftcard para {$recipientLabel} fue canjeada";

            $this->mail->send(
                (string) $full['sender_email'],
                (string) ($full['sender_name'] ?? ''),
                $subject,
                $html,
            );
        } catch (\Throwable $e) {
            error_log('[RedemptionController] Falló notificar sender: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/redeem/lookup?short=XXXXXXXX — usado por la vista /scan
     * cuando el operador tipea el código corto manualmente.
     */
    public function lookupShort(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenant = $this->tenant($request);
        $short  = (string) ($request->getQueryParams()['short'] ?? '');

        $row = $this->repo->findByShortTokenForTenant($short, $tenant);
        if ($row === null) {
            return ApiResponse::notFound($response, 'Código no encontrado.');
        }
        if ($row === []) {
            return ApiResponse::error(
                $response,
                'El código corto coincide con varias giftcards. Escaneá el QR completo o usá el token de 32 caracteres.',
                409
            );
        }
        return ApiResponse::json($response, ['token' => $row['token']]);
    }

    // ---------- helpers ----------

    private function tenant(ServerRequestInterface $request): int
    {
        $t = $request->getAttribute(TenantMiddleware::REQUEST_ATTR);
        if (!is_int($t)) {
            throw new \RuntimeException('Tenant no inyectado.');
        }
        return $t;
    }

    private function currentUser(ServerRequestInterface $request): AuthenticatedUser
    {
        $u = $request->getAttribute(AuthMiddleware::REQUEST_ATTR);
        if (!$u instanceof AuthenticatedUser) {
            throw new \RuntimeException('Usuario no inyectado.');
        }
        return $u;
    }

    private function isExpired(mixed $date): bool
    {
        if (!is_string($date) || $date === '') {
            return false;
        }
        $today = date('Y-m-d');
        return $date < $today;
    }

    private function ipFrom(ServerRequestInterface $request): ?string
    {
        $params = $request->getServerParams();
        $ip     = $params['REMOTE_ADDR'] ?? null;
        if (!is_string($ip) || $ip === '') {
            return null;
        }
        return substr($ip, 0, 45);
    }
}
