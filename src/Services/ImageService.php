<?php

declare(strict_types=1);

namespace App\Services;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Psr\Http\Message\UploadedFileInterface;

final class ImageService
{
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    private const EXT_MAP = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly string $uploadsBasePath,
        private readonly int $maxBytes = 2 * 1024 * 1024,
    ) {
    }

    public function validate(UploadedFileInterface $file): void
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Error al subir el archivo (código ' . $file->getError() . ').');
        }
        $size = $file->getSize();
        if ($size !== null && $size > $this->maxBytes) {
            $maxMb = (int) round($this->maxBytes / 1024 / 1024);
            throw new \RuntimeException("El archivo supera los {$maxMb} MB permitidos.");
        }
        $mime = $this->detectMime($file);
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            throw new \RuntimeException('Formato no permitido. Usá JPG, PNG o WebP.');
        }
    }

    /**
     * Procesa la imagen (cover crop al tamaño dado) y la guarda bajo
     * uploads/establishments/{id}/logo.{ext}. Devuelve el path relativo
     * (sin "public/") apto para guardar en logo_path.
     */
    public function saveSquareLogo(UploadedFileInterface $file, int $establishmentId, int $size = 400): string
    {
        $this->validate($file);

        $mime = $this->detectMime($file);
        $ext  = self::EXT_MAP[$mime];

        $relDir = 'establishments/' . $establishmentId;
        $absDir = rtrim($this->uploadsBasePath, '/') . '/' . $relDir;
        $this->ensureDir($absDir);

        $relPath = $relDir . '/logo.' . $ext;
        $absPath = rtrim($this->uploadsBasePath, '/') . '/' . $relPath;

        $stream = $file->getStream();
        $stream->rewind();
        $contents = $stream->getContents();

        $manager = new ImageManager(new Driver());
        $image   = $manager->read($contents);
        $image->cover($size, $size);
        $image->save($absPath, 85);

        // Borrar logos previos con extensión distinta para no dejar huérfanos.
        foreach (self::EXT_MAP as $otherExt) {
            $other = $absDir . '/logo.' . $otherExt;
            if ($other !== $absPath && is_file($other)) {
                @unlink($other);
            }
        }

        return $relPath;
    }

    public function deleteLogoForEstablishment(int $establishmentId): void
    {
        $absDir = rtrim($this->uploadsBasePath, '/') . '/establishments/' . $establishmentId;
        foreach (self::EXT_MAP as $ext) {
            $file = $absDir . '/logo.' . $ext;
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function detectMime(UploadedFileInterface $file): string
    {
        $stream = $file->getStream();
        $stream->rewind();
        $header = $stream->read(12);
        $stream->rewind();

        if (str_starts_with($header, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($header, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }
        if (str_starts_with($header, 'RIFF') && substr($header, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        return 'application/octet-stream';
    }

    private function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("No se pudo crear el directorio: {$dir}");
        }
    }
}
