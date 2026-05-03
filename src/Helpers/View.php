<?php

declare(strict_types=1);

namespace App\Helpers;

use Psr\Http\Message\ResponseInterface;

final class View
{
    public function __construct(private readonly string $viewsDir)
    {
    }

    public function render(ResponseInterface $response, string $template, array $data = []): ResponseInterface
    {
        $html = $this->capture($template, $data);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function withLayout(ResponseInterface $response, string $template, string $layout, array $data = []): ResponseInterface
    {
        $content = $this->capture($template, $data);
        $html = $this->capture($layout, array_merge($data, ['content' => $content]));
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function capture(string $template, array $data): string
    {
        $file = $this->viewsDir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Vista no encontrada: {$template}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
