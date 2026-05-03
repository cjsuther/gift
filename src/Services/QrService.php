<?php

declare(strict_types=1);

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;

final class QrService
{
    public function __construct(
        private readonly string $appUrl,
        private readonly int $defaultSize = 400,
        private readonly int $defaultMargin = 12,
    ) {
    }

    /**
     * URL canónica del QR para el token. Es lo que escaneás con cualquier
     * lector estándar y te lleva a la vista de canje.
     */
    public function urlForToken(string $token): string
    {
        return rtrim($this->appUrl, '/') . '/redeem/' . $token;
    }

    /**
     * PNG del QR (binario crudo). Útil para servirlo como descarga o stream.
     */
    public function pngForToken(string $token, ?int $size = null): string
    {
        return $this->build($this->urlForToken($token), $size ?? $this->defaultSize);
    }

    /**
     * Data URI listo para embedder en <img src="..."> o en HTML→PDF (mPDF).
     */
    public function dataUriForToken(string $token, ?int $size = null): string
    {
        $bytes = $this->pngForToken($token, $size);
        return 'data:image/png;base64,' . base64_encode($bytes);
    }

    private function build(string $data, int $size): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($data)
            ->encoding(new Encoding('UTF-8'))
            ->size($size)
            ->margin($this->defaultMargin)
            ->build();

        return $result->getString();
    }
}
