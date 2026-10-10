<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\Exceptions\ValidationException;
use App\Domain\ValueObjects\Quantity;
use PHPUnit\Framework\TestCase;

final class QuantityTest extends TestCase
{
    public function test_valid_quantity_is_accepted(): void
    {
        $q = new Quantity(5);
        $this->assertSame(5, $q->value());

        $zero = new Quantity(0);
        $this->assertSame(0, $zero->value());
    }

    public function test_negative_quantity_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('La cantidad no puede ser negativa.');

        new Quantity(-1);
    }

    public function test_positive_factory_rejects_zero(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('La cantidad debe ser mayor o igual a 1.');

        Quantity::positive(0);
    }

    public function test_positive_factory_accepts_one_or_more(): void
    {
        $q = Quantity::positive(1);
        $this->assertSame(1, $q->value());
    }

    public function test_quantity_arithmetic(): void
    {
        $q = new Quantity(5);
        $this->assertSame(8, $q->add(3)->value());
        $this->assertSame(3, $q->subtract(2)->value());
    }
}
