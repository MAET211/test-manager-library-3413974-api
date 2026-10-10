<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\Loan;
use App\Domain\Entities\LoanItem;
use App\Domain\Exceptions\DuplicateBookInLoanException;
use App\Domain\Exceptions\ItemAlreadyReturnedException;
use App\Domain\Exceptions\ValidationException;
use App\Domain\ValueObjects\LoanStatus;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class LoanTest extends TestCase
{
    private DateTimeZone $utc;

    protected function setUp(): void
    {
        $this->utc = new DateTimeZone('UTC');
    }

    private function createItem(
        string $id,
        string $bookId,
        DateTimeImmutable $loanedAt,
        string $title = 'Libro de Prueba',
        ?DateTimeImmutable $returnedAt = null
    ): LoanItem {
        return new LoanItem(
            id: $id,
            loanId: '10000000-0000-0000-0000-000000000000',
            bookId: $bookId,
            bookTitle: $title,
            isbn: '9780307474728',
            categoryName: 'Novela',
            dueAt: $loanedAt->modify('+14 days'),
            returnedAt: $returnedAt
        );
    }

    public function test_loan_creation_with_valid_items_and_14_days_due_date(): void
    {
        $loanedAt = new DateTimeImmutable('2026-10-01 10:00:00', $this->utc);
        $expectedDue = new DateTimeImmutable('2026-10-15 10:00:00', $this->utc);

        $item1 = $this->createItem('item-1', 'book-1', $loanedAt);
        $item2 = $this->createItem('item-2', 'book-2', $loanedAt);

        $loan = new Loan(
            id: 'loan-1',
            memberId: 'member-1',
            loanedByUserId: 'user-1',
            loanedAt: $loanedAt,
            items: [$item1, $item2]
        );

        $this->assertCount(2, $loan->items());
        $this->assertSame(2, $loan->activeItemsCount());
        $this->assertEquals($expectedDue, $loan->items()[0]->dueAt());
    }

    public function test_loan_cannot_have_zero_items(): void
    {
        $loanedAt = new DateTimeImmutable('now', $this->utc);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Un préstamo debe contener entre 1 y 3 libros (se recibieron 0).');

        new Loan(
            id: 'loan-1',
            memberId: 'member-1',
            loanedByUserId: 'user-1',
            loanedAt: $loanedAt,
            items: []
        );
    }

    public function test_loan_cannot_have_more_than_three_items(): void
    {
        $loanedAt = new DateTimeImmutable('now', $this->utc);

        $items = [
            $this->createItem('item-1', 'book-1', $loanedAt),
            $this->createItem('item-2', 'book-2', $loanedAt),
            $this->createItem('item-3', 'book-3', $loanedAt),
            $this->createItem('item-4', 'book-4', $loanedAt),
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Un préstamo debe contener entre 1 y 3 libros (se recibieron 4).');

        new Loan(
            id: 'loan-1',
            memberId: 'member-1',
            loanedByUserId: 'user-1',
            loanedAt: $loanedAt,
            items: $items
        );
    }

    public function test_loan_rejects_duplicate_books_in_the_same_loan(): void
    {
        $loanedAt = new DateTimeImmutable('now', $this->utc);

        $items = [
            $this->createItem('item-1', 'book-duplicate', $loanedAt),
            $this->createItem('item-2', 'book-duplicate', $loanedAt),
        ];

        $this->expectException(DuplicateBookInLoanException::class);
        $this->expectExceptionMessage('No se puede incluir el mismo libro más de una vez en un préstamo');

        new Loan(
            id: 'loan-1',
            memberId: 'member-1',
            loanedByUserId: 'user-1',
            loanedAt: $loanedAt,
            items: $items
        );
    }

    public function test_return_item_marks_it_as_returned(): void
    {
        $loanedAt = new DateTimeImmutable('2026-10-01 10:00:00', $this->utc);
        $returnedAt = new DateTimeImmutable('2026-10-05 15:30:00', $this->utc);

        $item1 = $this->createItem('item-1', 'book-1', $loanedAt);
        $loan = new Loan('loan-1', 'member-1', 'user-1', $loanedAt, [$item1]);

        $this->assertFalse($item1->isReturned());

        $loan->returnItem('item-1', $returnedAt);

        $this->assertTrue($item1->isReturned());
        $this->assertSame($returnedAt, $item1->returnedAt());
        $this->assertSame(0, $loan->activeItemsCount());
    }

    public function test_returning_an_already_returned_item_throws_item_already_returned_exception(): void
    {
        $loanedAt = new DateTimeImmutable('2026-10-01 10:00:00', $this->utc);
        $returnedAt = new DateTimeImmutable('2026-10-05 15:30:00', $this->utc);

        $item1 = $this->createItem('item-1', 'book-1', $loanedAt, 'Libro 1', $returnedAt);
        $loan = new Loan('loan-1', 'member-1', 'user-1', $loanedAt, [$item1]);

        $this->expectException(ItemAlreadyReturnedException::class);
        $this->expectExceptionMessage('La línea del libro «Libro 1» ya fue devuelta previamente.');

        $loan->returnItem('item-1', new DateTimeImmutable('2026-10-06 10:00:00', $this->utc));
    }

    public function test_calculated_status_is_open_when_items_not_due(): void
    {
        $loanedAt = new DateTimeImmutable('2026-10-01 10:00:00', $this->utc);
        $now = new DateTimeImmutable('2026-10-10 10:00:00', $this->utc); // due es el 15, aún vigente

        $item1 = $this->createItem('item-1', 'book-1', $loanedAt);
        $loan = new Loan('loan-1', 'member-1', 'user-1', $loanedAt, [$item1]);

        $this->assertSame(LoanStatus::OPEN, $loan->status($now));
    }

    public function test_calculated_status_is_overdue_when_any_unreturned_item_is_due(): void
    {
        $loanedAt = new DateTimeImmutable('2026-10-01 10:00:00', $this->utc);
        $now = new DateTimeImmutable('2026-10-20 10:00:00', $this->utc); // due fue el 15, ahora es 20 (vencido)

        $item1 = $this->createItem('item-1', 'book-1', $loanedAt);
        $loan = new Loan('loan-1', 'member-1', 'user-1', $loanedAt, [$item1]);

        $this->assertSame(LoanStatus::OVERDUE, $loan->status($now));
        $this->assertTrue($item1->isOverdue($now));
        $this->assertSame(5, $item1->daysLate($now));
    }

    public function test_calculated_status_is_closed_when_all_items_are_returned(): void
    {
        $loanedAt = new DateTimeImmutable('2026-10-01 10:00:00', $this->utc);
        $returnedAt = new DateTimeImmutable('2026-10-05 10:00:00', $this->utc);
        $now = new DateTimeImmutable('2026-10-20 10:00:00', $this->utc);

        $item1 = $this->createItem('item-1', 'book-1', $loanedAt, 'Libro 1', $returnedAt);
        $item2 = $this->createItem('item-2', 'book-2', $loanedAt, 'Libro 2', $returnedAt);
        $loan = new Loan('loan-1', 'member-1', 'user-1', $loanedAt, [$item1, $item2]);

        $this->assertSame(LoanStatus::CLOSED, $loan->status($now));
    }
}
