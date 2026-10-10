<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\Exceptions\ValidationException;
use App\Domain\ValueObjects\Isbn;
use PHPUnit\Framework\TestCase;

final class IsbnTest extends TestCase
{
    public function test_valid_13_digit_numeric_isbn_is_accepted(): void
    {
        $isbn = new Isbn('9780307474728');

        $this->assertSame('9780307474728', $isbn->value());
        $this->assertSame('9780307474728', (string) $isbn);
    }

    public function test_isbn_with_whitespace_is_trimmed(): void
    {
        $isbn = new Isbn('  9780307474728  ');

        $this->assertSame('9780307474728', $isbn->value());
    }

    public function test_isbn_with_less_than_13_digits_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('El ISBN debe tener exactamente 13 dígitos numéricos.');

        new Isbn('978030747472');
    }

    public function test_isbn_with_more_than_13_digits_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        new Isbn('97803074747289');
    }

    public function test_isbn_with_letters_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        new Isbn('978030747472X');
    }

    public function test_isbn_equality(): void
    {
        $isbn1 = new Isbn('9780307474728');
        $isbn2 = new Isbn('9780307474728');
        $isbn3 = new Isbn('9780307474729');

        $this->assertTrue($isbn1->equals($isbn2));
        $this->assertFalse($isbn1->equals($isbn3));
    }
}
