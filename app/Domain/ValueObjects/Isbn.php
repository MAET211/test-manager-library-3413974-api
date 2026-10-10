<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use App\Domain\Exceptions\ValidationException;

final readonly class Isbn
{
    private string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);

        if (!preg_match('/^\d{13}$/', $trimmed)) {
            throw new ValidationException(
                message: 'El ISBN debe tener exactamente 13 dígitos numéricos.',
                details: [['field' => 'isbn', 'issue' => 'debe contener exactamente 13 dígitos numéricos']]
            );
        }

        $this->value = $trimmed;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
