<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use App\Domain\Exceptions\ValidationException;

enum UserRole: string
{
    case ADMIN = 'admin';
    case LIBRARIAN = 'librarian';

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'admin' => self::ADMIN,
            'librarian' => self::LIBRARIAN,
            default => throw new ValidationException(
                message: "Rol inválido: «{$value}». Los roles permitidos son «admin» o «librarian».",
                details: [['field' => 'role', 'issue' => 'debe ser admin o librarian']]
            ),
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    public function isLibrarian(): bool
    {
        return $this === self::LIBRARIAN;
    }
}
