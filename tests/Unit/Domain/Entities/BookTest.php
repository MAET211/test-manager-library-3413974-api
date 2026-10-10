<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\Book;
use App\Domain\Exceptions\BookHasOpenLoansException;
use App\Domain\Exceptions\BookNotAvailableException;
use App\Domain\Exceptions\ValidationException;
use App\Domain\ValueObjects\Isbn;
use PHPUnit\Framework\TestCase;

final class BookTest extends TestCase
{
    private function createSampleBook(int $total = 5, ?int $available = null): Book
    {
        return new Book(
            id: '123e4567-e89b-12d3-a456-426614174000',
            title: 'Cien años de soledad',
            author: 'Gabriel García Márquez',
            isbn: new Isbn('9780307474728'),
            categoryId: '11111111-1111-4111-8111-111111111111',
            totalCopies: $total,
            availableCopies: $available
        );
    }

    public function test_book_instantiates_with_available_copies_equal_to_total_if_omitted(): void
    {
        $book = $this->createSampleBook(total: 4);

        $this->assertSame(4, $book->totalCopies());
        $this->assertSame(4, $book->availableCopies());
        $this->assertNull($book->deletedAt());
        $this->assertFalse($book->isDeleted());
    }

    public function test_withdraw_decrements_available_copies(): void
    {
        $book = $this->createSampleBook(total: 3, available: 3);

        $book->withdraw();

        $this->assertSame(2, $book->availableCopies());
    }

    public function test_withdraw_when_no_copies_available_throws_book_not_available_exception(): void
    {
        $book = $this->createSampleBook(total: 2, available: 0);

        $this->expectException(BookNotAvailableException::class);
        $this->expectExceptionMessage('No quedan ejemplares disponibles de «Cien años de soledad».');

        $book->withdraw();
    }

    public function test_withdraw_when_book_is_soft_deleted_throws_book_not_available_exception(): void
    {
        $book = $this->createSampleBook(total: 2, available: 2);
        $book->softDelete(hasOpenLoans: false);

        $this->expectException(BookNotAvailableException::class);

        $book->withdraw();
    }

    public function test_restock_increments_available_copies(): void
    {
        $book = $this->createSampleBook(total: 5, available: 3);

        $book->restock();

        $this->assertSame(4, $book->availableCopies());
    }

    public function test_restock_cannot_exceed_total_copies(): void
    {
        $book = $this->createSampleBook(total: 4, available: 4);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No se puede devolver el ejemplar: los ejemplares disponibles (4) ya alcanzaron el total (4).');

        $book->restock();
    }

    public function test_update_details_adjusts_available_copies_correctly(): void
    {
        // Total 5, disponibles 3 (2 prestados)
        $book = $this->createSampleBook(total: 5, available: 3);

        // Aumentamos total a 8 (+3), disponibles deben pasar a 6 (+3)
        $book->updateDetails(
            title: 'Cien años de soledad (Edición Ilustrada)',
            author: 'Gabriel García Márquez',
            categoryId: '11111111-1111-4111-8111-111111111111',
            newTotalCopies: 8
        );

        $this->assertSame(8, $book->totalCopies());
        $this->assertSame(6, $book->availableCopies());
        $this->assertSame('Cien años de soledad (Edición Ilustrada)', $book->title());
    }

    public function test_update_details_fails_if_available_would_become_negative(): void
    {
        // Total 4, disponibles 1 (3 libros prestados)
        $book = $this->createSampleBook(total: 4, available: 1);

        // Reducir total a 2 restaría 2 a disponibles (1 - 2 = -1) -> debe fallar con 422
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('El ajuste solicitado dejaría los ejemplares disponibles en negativo (-1).');

        $book->updateDetails(
            title: 'Cien años de soledad',
            author: 'Gabriel García Márquez',
            categoryId: '11111111-1111-4111-8111-111111111111',
            newTotalCopies: 2
        );
    }

    public function test_soft_delete_with_open_loans_throws_book_has_open_loans_exception(): void
    {
        $book = $this->createSampleBook(total: 3, available: 1);

        $this->expectException(BookHasOpenLoansException::class);
        $this->expectExceptionMessage('No se puede dar de baja el libro «Cien años de soledad» porque tiene préstamos abiertos.');

        $book->softDelete(hasOpenLoans: true);
    }

    public function test_soft_delete_without_open_loans_succeeds(): void
    {
        $book = $this->createSampleBook(total: 3, available: 3);

        $book->softDelete(hasOpenLoans: false);

        $this->assertTrue($book->isDeleted());
        $this->assertNotNull($book->deletedAt());
    }

    public function test_cover_key_validations(): void
    {
        $book = $this->createSampleBook();

        $book->setCover('covers/novel-1.jpg');
        $this->assertSame('covers/novel-1.jpg', $book->coverKey());

        $book->removeCover();
        $this->assertNull($book->coverKey());

        $this->expectException(ValidationException::class);
        $book->setCover('');
    }
}
