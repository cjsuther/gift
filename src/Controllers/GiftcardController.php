<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\AuthenticatedUser;
use App\Helpers\Response as ApiResponse;
use App\Helpers\Validator;
use App\Helpers\View;
use App\Middleware\AuthMiddleware;
use App\Middleware\TenantMiddleware;
use App\Repositories\EstablishmentRepository;
use App\Repositories\GiftcardRepository;
use App\Services\ImageService;
use App\Services\MailService;
use App\Services\QrService;
use App\Services\TokenService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * CRUD de giftcards. Tenant-scoped: el establishment_id viene de TenantMiddleware,
 * jamás del body. Permisos por método los maneja el routing en app.php.
 */
final class GiftcardController
{
    public function __construct(
        private readonly GiftcardRepository $repo,
        private readonly TokenService $tokens,
        private readonly QrService $qr,
        private readonly ?ImageService $images = null,
        private readonly ?MailService $mail = null,
        private readonly ?View $view = null,
        private readonly ?EstablishmentRepository $establishments = null,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenant = $this->tenant($request);
        $q      = $request->getQueryParams();

        $page = $this->repo->listForTenant($tenant, [
            'q'        => $q['q']        ?? null,
            'status'   => $q['status']   ?? null,
            'sort'     => $q['sort']     ?? null,
            'page'     => isset($q['page'])     ? (int) $q['page']     : 1,
            'per_page' => isset($q['per_page']) ? (int) $q['per_page'] : null,
        ]);

        return ApiResponse::json($response, $page);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenant = $this->tenant($request);
        $row    = $this->repo->findForTenant((int) ($args['id'] ?? 0), $tenant);
        if ($row === null) {
            return ApiResponse::notFound($response, 'Giftcard no encontrada.');
        }
        return ApiResponse::json($response, [
            'giftcard'    => $row,
            'redeem_url'  => $this->qr->urlForToken((string) $row['token']),
            'token_short' => TokenService::shortLabel((string) $row['token']),
        ]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $tenant = $this->tenant($request);
        $user   = $this->currentUser($request);
        $data   = $this->parseBody($request);

        $v = (new Validator($data))
            ->required('title')->minLength('title', 2);
        if (array_key_exists('sender_email', $data) && !empty($data['sender_email'])) {
            $v->email('sender_email');
        }
        if (array_key_exists('expires_at', $data) && $data['expires_at'] !== '' && !$this->validDate($data['expires_at'])) {
            return ApiResponse::error($response, 'Fecha de vencimiento inválida.', 422, [
                'fields' => ['expires_at' => 'Formato esperado: AAAA-MM-DD.'],
            ]);
        }
        if ($v->fails()) {
            return ApiResponse::error($response, 'Datos inválidos.', 422, ['fields' => $v->errors()]);
        }

        $token = $this->tokens->unique(fn (string $t) => $this->repo->tokenExists($t));

        $imageFile  = $this->extractUpload($request, 'image');
        $imageSaver = $this->imageSaverFor($imageFile, $tenant);

        try {
            $id = $this->repo->create($tenant, $user->id, $token, [
                'title'             => trim((string) $data['title']),
                'description'       => $this->nullable($data['description'] ?? null),
                'expires_at'        => $this->nullable($data['expires_at'] ?? null),
                'recipient_name'    => $this->nullable($data['recipient_name'] ?? null),
                'recipient_contact' => $this->nullable($data['recipient_contact'] ?? null),
                'sender_name'       => $this->nullable($data['sender_name'] ?? null),
                'sender_email'      => $this->nullable($data['sender_email'] ?? null),
            ], $imageSaver);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($response, $e->getMessage(), 422);
        }

        $row = $this->repo->findForTenant($id, $tenant);
        $persistedToken = (string) ($row['token'] ?? $token);
        return ApiResponse::json($response, [
            'giftcard'    => $row,
            'redeem_url'  => $this->qr->urlForToken($persistedToken),
            'token_short' => TokenService::shortLabel($persistedToken),
        ], 201);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenant = $this->tenant($request);
        $user   = $this->currentUser($request);
        $id     = (int) ($args['id'] ?? 0);
        $row    = $this->repo->findForTenant($id, $tenant);
        if ($row === null) {
            return ApiResponse::notFound($response, 'Giftcard no encontrada.');
        }
        if ($row['status'] !== 'active') {
            return ApiResponse::error(
                $response,
                'Solo podés editar giftcards en estado "vigente".',
                422,
                ['fields' => ['status' => 'Estado actual: ' . $row['status']]]
            );
        }

        $data = $this->parseBody($request);

        $v = new Validator($data);
        if (array_key_exists('title', $data)) { $v->minLength('title', 2); }
        if (array_key_exists('sender_email', $data) && !empty($data['sender_email'])) {
            $v->email('sender_email');
        }
        if (array_key_exists('expires_at', $data) && $data['expires_at'] !== '' && !$this->validDate($data['expires_at'])) {
            return ApiResponse::error($response, 'Fecha de vencimiento inválida.', 422, [
                'fields' => ['expires_at' => 'Formato esperado: AAAA-MM-DD.'],
            ]);
        }
        if ($v->fails()) {
            return ApiResponse::error($response, 'Datos inválidos.', 422, ['fields' => $v->errors()]);
        }

        $patch = [];
        foreach (['title', 'description', 'recipient_name', 'recipient_contact', 'sender_name', 'sender_email'] as $field) {
            if (array_key_exists($field, $data)) {
                $patch[$field] = $field === 'title' ? trim((string) $data[$field]) : $this->nullable($data[$field]);
            }
        }
        if (array_key_exists('expires_at', $data)) {
            $patch['expires_at'] = $this->nullable($data['expires_at']);
        }

        $imageFile  = $this->extractUpload($request, 'image');
        $imageSaver = $this->imageSaverFor($imageFile, $tenant);

        try {
            $this->repo->updateActive($id, $tenant, $user->id, $patch, $imageSaver);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($response, $e->getMessage(), 422);
        }

        return ApiResponse::json($response, ['giftcard' => $this->repo->findForTenant($id, $tenant)]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenant = $this->tenant($request);
        $user   = $this->currentUser($request);
        $id     = (int) ($args['id'] ?? 0);
        $row    = $this->repo->findForTenant($id, $tenant);
        if ($row === null) {
            return ApiResponse::notFound($response, 'Giftcard no encontrada.');
        }
        if ($row['status'] !== 'active') {
            return ApiResponse::error(
                $response,
                'Solo podés cancelar giftcards en estado "vigente".',
                422
            );
        }

        $this->repo->cancel($id, $tenant, $user->id);
        return ApiResponse::json($response, ['ok' => true]);
    }

    /**
     * Devuelve el QR como PNG binario (descarga).
     */
    public function qr(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenant = $this->tenant($request);
        $row    = $this->repo->findForTenant((int) ($args['id'] ?? 0), $tenant);
        if ($row === null) {
            return ApiResponse::notFound($response, 'Giftcard no encontrada.');
        }

        $size  = (int) ($request->getQueryParams()['size'] ?? 600);
        $size  = max(120, min(1200, $size));
        $bytes = $this->qr->pngForToken((string) $row['token'], $size);

        $response->getBody()->write($bytes);
        $filename = 'giftcard-' . TokenService::shortLabel((string) $row['token']) . '.png';
        return $response
            ->withHeader('Content-Type', 'image/png')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Cache-Control', 'private, max-age=300');
    }

    /**
     * POST /api/giftcards/{id}/send-email — envía la giftcard por email al destinatario.
     * Body: { email?: string } (opcional, override del recipient_contact si no es email válido).
     * Adjunta el QR como PNG y lo embebe en el HTML vía CID.
     */
    public function sendEmail(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if ($this->mail === null || $this->view === null) {
            return ApiResponse::error($response, 'Envío de email no disponible.', 503);
        }
        if (!$this->mail->isConfigured()) {
            return ApiResponse::error($response, 'El envío de email no está configurado en este servidor.', 503);
        }

        $tenant = $this->tenant($request);
        $id     = (int) ($args['id'] ?? 0);
        $row    = $this->repo->findForTenant($id, $tenant);
        if ($row === null) {
            return ApiResponse::notFound($response, 'Giftcard no encontrada.');
        }
        if ($row['status'] !== 'active') {
            return ApiResponse::error(
                $response,
                'Solo podés enviar giftcards en estado "vigente".',
                422,
                ['fields' => ['status' => 'Estado actual: ' . $row['status']]]
            );
        }

        $data        = $this->parseBody($request);
        $bodyEmail   = $this->nullable($data['email'] ?? null);
        $contactRaw  = (string) ($row['recipient_contact'] ?? '');
        $contactMail = filter_var($contactRaw, FILTER_VALIDATE_EMAIL) !== false ? $contactRaw : null;

        $toEmail = $bodyEmail ?? $contactMail;
        if ($toEmail === null || filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
            return ApiResponse::error(
                $response,
                'Necesitamos un email válido del destinatario.',
                422,
                ['fields' => ['email' => 'Ingresá un email válido o cargalo en el campo "Contacto destinatario".']]
            );
        }

        $token = (string) $row['token'];
        $est   = $this->establishments?->find((int) $row['establishment_id']);

        try {
            $qrPng = $this->qr->pngForToken($token);
        } catch (\Throwable $e) {
            error_log('[GiftcardController::sendEmail] Falló generar QR: ' . $e->getMessage());
            return ApiResponse::error($response, 'No se pudo generar el QR.', 500);
        }

        $html = $this->view->renderToString('emails/giftcard_share', [
            'giftcard'      => $row,
            'establishment' => $est,
            'redeemUrl'     => $this->qr->urlForToken($token),
            'tokenShort'    => TokenService::shortLabel($token),
            'qrCid'         => 'giftcard-qr',
        ]);

        $senderLabel = !empty($row['sender_name'])
            ? (string) $row['sender_name']
            : (string) ($est['name'] ?? 'Giftcards');
        $subject = 'Tenés una giftcard de ' . $senderLabel;

        $ok = $this->mail->send(
            $toEmail,
            (string) ($row['recipient_name'] ?? ''),
            $subject,
            $html,
            null,
            [[
                'cid'      => 'giftcard-qr',
                'data'     => $qrPng,
                'filename' => 'giftcard-qr.png',
                'mime'     => 'image/png',
            ]],
            [[
                'data'     => $qrPng,
                'filename' => 'giftcard-' . TokenService::shortLabel($token) . '.png',
                'mime'     => 'image/png',
            ]],
        );

        if (!$ok) {
            return ApiResponse::error($response, 'No se pudo enviar el email. Revisá la configuración SMTP.', 502);
        }

        return ApiResponse::json($response, ['ok' => true, 'sent_to' => $toEmail]);
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

    private function extractUpload(ServerRequestInterface $request, string $field): ?UploadedFileInterface
    {
        $files = $request->getUploadedFiles();
        $file  = $files[$field] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            return null;
        }
        if ($file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    private function imageSaverFor(?UploadedFileInterface $file, int $establishmentId): ?\Closure
    {
        if ($file === null || $this->images === null) {
            return null;
        }
        return fn (string $token) => $this->images->saveGiftcardImage($file, $establishmentId, $token);
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = trim((string) $value);
        return $v === '' ? null : $v;
    }

    private function validDate(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $d !== false && $d->format('Y-m-d') === $value;
    }
}
