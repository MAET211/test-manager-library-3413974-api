<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use App\Domain\Exceptions\ValidationException;

enum LoanStatus: string
{
    case OPEN = 'open';
    case OVERDUE = 'overdue';
    case CLOSED = 'closed';

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'open' => self::OPEN,
            'overdue' => self::OVERDUE,
            'closed' => self::CLOSED,
            default => throw new ValidationException(
                message: "Estado de préstamo inválido: «{$value}».",
                details: [['field' => 'status', 'issue' => 'debe ser open, overdue o closed']]
            ),
        };
    }
}
