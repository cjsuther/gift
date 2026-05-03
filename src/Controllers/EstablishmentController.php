<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Response as ApiResponse;
use App\Helpers\Slug;
use App\Helpers\Validator;
use App\Repositories\EstablishmentRepository;
use App\Services\ImageService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

final class EstablishmentController
{
    public function __construct(
        private readonly EstablishmentRepository $repo,
        private readonly ?ImageService $images = null,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $q = $request->getQueryParams()['q'] ?? null;
        return ApiResponse::json($response, [
            'establishments' => $this->repo->list(is_string($q) ? $q : null),
        ]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id   = (int) ($args['id'] ?? 0);
        $row  = $this->repo->find($id);
        if ($row === null) {
            return ApiResponse::notFound($response, 'Establecimiento no encontrado.');
        }
        return ApiResponse::json($response, ['establishment' => $row]);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $this->parseBody($request);

        $v = (new Validator($data))
            ->required('name')->minLength('name', 2)
            ->required('admin_name')->minLength('admin_name', 2)
            ->required('admin_email')->email('admin_email')
            ->required('admin_password')->minLength('admin_password', 8);

        if ($v->fails()) {
            return ApiResponse::error($response, 'Datos inválidos.', 422, ['fields' => $v->errors()]);
        }

        if ($this->repo->emailExists((string) $data['admin_email'])) {
            return ApiResponse::error($response, 'Ya existe un usuario con ese email.', 422, [
                'fields' => ['admin_email' => 'Email ya registrado.'],
            ]);
        }

        $slug = Slug::unique(
            (string) $data['name'],
            fn(string $s) => $this->repo->slugExists($s),
        );

        $logoFile  = $this->extractUpload($request, 'logo');
        $logoSaver = $this->logoSaverFor($logoFile);

        try {
            $result = $this->repo->createWithAdmin(
                data: [
                    'name'          => trim((string) $data['name']),
                    'slug'          => $slug,
                    'address'       => $this->nullable($data['address'] ?? null),
                    'phone'         => $this->nullable($data['phone'] ?? null),
                    'email'         => $this->nullable($data['email'] ?? null),
                    'primary_color' => $this->validHexColor($data['primary_color'] ?? null) ?? '#111827',
                ],
                admin: [
                    'name'     => trim((string) $data['admin_name']),
                    'email'    => trim((string) $data['admin_email']),
                    'password' => (string) $data['admin_password'],
                ],
                logoSaver: $logoSaver,
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error($response, $e->getMessage(), 422);
        }

        $row = $this->repo->find($result['establishment_id']);
        return ApiResponse::json($response, [
            'establishment' => $row,
            'admin_user_id' => $result['admin_user_id'],
        ], 201);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id  = (int) ($args['id'] ?? 0);
        $row = $this->repo->find($id);
        if ($row === null) {
            return ApiResponse::notFound($response, 'Establecimiento no encontrado.');
        }

        $data = $this->parseBody($request);

        $v = new Validator($data);
        if (array_key_exists('name', $data)) {
            $v->minLength('name', 2);
        }
        if ($v->fails()) {
            return ApiResponse::error($response, 'Datos inválidos.', 422, ['fields' => $v->errors()]);
        }

        $patch = [];

        if (array_key_exists('name', $data) && trim((string) $data['name']) !== $row['name']) {
            $patch['name'] = trim((string) $data['name']);
            $patch['slug'] = Slug::unique(
                $patch['name'],
                fn(string $s) => $this->repo->slugExists($s, $id),
            );
        }

        foreach (['address', 'phone', 'email'] as $field) {
            if (array_key_exists($field, $data)) {
                $patch[$field] = $this->nullable($data[$field]);
            }
        }

        if (array_key_exists('primary_color', $data)) {
            $color = $this->validHexColor($data['primary_color']);
            if ($color !== null) {
                $patch['primary_color'] = $color;
            }
        }

        if (array_key_exists('is_active', $data)) {
            $patch['is_active'] = (int) (bool) $data['is_active'];
        }

        $logoFile  = $this->extractUpload($request, 'logo');
        $logoSaver = $this->logoSaverFor($logoFile);

        try {
            $this->repo->update($id, $patch, $logoSaver);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($response, $e->getMessage(), 422);
        }

        return ApiResponse::json($response, ['establishment' => $this->repo->find($id)]);
    }

    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id  = (int) ($args['id'] ?? 0);
        $row = $this->repo->find($id);
        if ($row === null) {
            return ApiResponse::notFound($response, 'Establecimiento no encontrado.');
        }
        $this->repo->deactivate($id);
        return ApiResponse::json($response, ['ok' => true]);
    }

    // ---------- helpers internos ----------

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

    private function logoSaverFor(?UploadedFileInterface $file): ?\Closure
    {
        if ($file === null || $this->images === null) {
            return null;
        }
        return fn(int $estId) => $this->images->saveSquareLogo($file, $estId);
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = trim((string) $value);
        return $v === '' ? null : $v;
    }

    private function validHexColor(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $v = trim($value);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $v) !== 1) {
            return null;
        }
        return strtolower($v);
    }
}
