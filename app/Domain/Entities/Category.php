<?php

declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\Exceptions\ValidationException;

final class Category
{
    private string $id;
    private string $name;

    public function __construct(string $id, string $name)
    {
        $trimmedName = trim($name);

        if ($trimmedName === '') {
            throw new ValidationException(
                message: 'El nombre de la categoría no puede estar vacío.',
                details: [['field' => 'name', 'issue' => 'no puede estar vacío']]
            );
        }

        if (mb_strlen($trimmedName) > 120) {
            throw new ValidationException(
                message: 'El nombre de la categoría no puede superar los 120 caracteres.',
                details: [['field' => 'name', 'issue' => 'longitud máxima 120']]
            );
        }

        $this->id = $id;
        $this->name = $trimmedName;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }
}
