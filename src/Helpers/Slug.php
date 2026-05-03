<?php

declare(strict_types=1);

namespace App\Helpers;

final class Slug
{
    public static function from(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        // Mapa explícito de acentos comunes (más predecible que iconv//TRANSLIT,
        // que en algunas plataformas inserta apóstrofes al transliterar).
        $text = strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
            'ç' => 'c', 'Ç' => 'C',
        ]);

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                $text = $converted;
            }
        }

        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $text = trim($text, '-');

        if ($text === '') {
            $text = 'item-' . substr(bin2hex(random_bytes(3)), 0, 6);
        }

        return substr($text, 0, 150);
    }

    /**
     * Itera variantes hasta encontrar una que pase el callback de existencia.
     * $existsFn recibe un slug y debe devolver true si ya está tomado.
     */
    public static function unique(string $base, callable $existsFn): string
    {
        $slug = self::from($base);
        if ($slug === '' || !$existsFn($slug)) {
            return $slug;
        }

        for ($i = 2; $i < 1000; $i++) {
            $candidate = substr($slug, 0, 145) . '-' . $i;
            if (!$existsFn($candidate)) {
                return $candidate;
            }
        }

        return $slug . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
    }
}
