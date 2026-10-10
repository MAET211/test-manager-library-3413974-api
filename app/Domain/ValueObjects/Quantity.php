<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

use App\Domain\Exceptions\ValidationException;

final readonly class Quantity
{
    private int $value;

    public function __construct(int $value)
    {
        if ($value < 0) {
            throw new ValidationException(
                message: 'La cantidad no puede ser negativa.',
                details: [['field' => 'quantity', 'issue' => 'debe ser mayor o igual a 0']]
            );
        }

        $this->value = $value;
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public static function positive(int $value): self
    {
        if ($value < 1) {
            throw new ValidationException(
                message: 'La cantidad debe ser mayor o igual a 1.',
                details: [['field' => 'total_copies', 'issue' => 'debe ser mayor o igual a 1']]
            );
        }

        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function add(int $delta): self
    {
        return new self($this->value + $delta);
    }

    public function subtract(int $delta): self
    {
        return new self($this->value - $delta);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
