<?php

declare(strict_types=1);

namespace App\Helpers;

final class Validator
{
    private array $errors = [];

    public function __construct(private readonly array $data)
    {
    }

    public function required(string $field, ?string $message = null): self
    {
        $value = $this->data[$field] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            $this->errors[$field] = $message ?? "El campo {$field} es obligatorio.";
        }
        return $this;
    }

    public function email(string $field, ?string $message = null): self
    {
        $value = $this->data[$field] ?? null;
        if (is_string($value) && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?? "El campo {$field} debe ser un email válido.";
        }
        return $this;
    }

    public function minLength(string $field, int $min, ?string $message = null): self
    {
        $value = $this->data[$field] ?? null;
        if (is_string($value) && mb_strlen($value) < $min) {
            $this->errors[$field] = $message ?? "El campo {$field} debe tener al menos {$min} caracteres.";
        }
        return $this;
    }

    public function in(string $field, array $allowed, ?string $message = null): self
    {
        $value = $this->data[$field] ?? null;
        if ($value !== null && !in_array($value, $allowed, true)) {
            $this->errors[$field] = $message ?? "El campo {$field} tiene un valor inválido.";
        }
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
