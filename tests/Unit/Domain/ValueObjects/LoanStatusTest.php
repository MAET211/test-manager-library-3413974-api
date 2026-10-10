<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\Exceptions\ValidationException;
use App\Domain\ValueObjects\LoanStatus;
use PHPUnit\Framework\TestCase;

final class LoanStatusTest extends TestCase
{
    public function test_parses_valid_loan_statuses(): void
    {
        $this->assertSame(LoanStatus::OPEN, LoanStatus::fromString('open'));
        $this->assertSame(LoanStatus::OVERDUE, LoanStatus::fromString('overdue'));
        $this->assertSame(LoanStatus::CLOSED, LoanStatus::fromString('closed'));
    }

    public function test_invalid_status_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Estado de préstamo inválido: «pending».');

        LoanStatus::fromString('pending');
    }
}
