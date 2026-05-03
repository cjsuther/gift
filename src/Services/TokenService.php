<?php

declare(strict_types=1);

namespace App\Services;

final class TokenService
{
    public const LENGTH_BYTES = 16;          // → 32 caracteres hex
    public const MAX_ATTEMPTS = 10;          // colisión hex(16) es ~2^-128, esto es paranoia

    /**
     * Genera un token único contra un callable de existencia.
     * $existsFn recibe un token candidato y devuelve true si ya está en la DB.
     */
    public function unique(callable $existsFn): string
    {
        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $candidate = self::generate();
            if (!$existsFn($candidate)) {
                return $candidate;
            }
        }
        // Astronómicamente improbable. Si llega acá, es bug en $existsFn o en random_bytes.
        throw new \RuntimeException('No se pudo generar un token único después de ' . self::MAX_ATTEMPTS . ' intentos.');
    }

    public static function generate(): string
    {
        return bin2hex(random_bytes(self::LENGTH_BYTES));
    }

    /**
     * Helper para mostrar versión corta legible (8 chars en mayúsculas).
     * Sirve como fallback manual si el QR no escanea bien.
     */
    public static function shortLabel(string $token): string
    {
        return strtoupper(substr($token, 0, 8));
    }
}
