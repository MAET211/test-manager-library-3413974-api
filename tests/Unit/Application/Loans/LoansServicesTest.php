<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Loans;

use App\Application\Services\Loans\GetLoanService;
use App\Application\Services\Loans\LendBooksService;
use App\Application\Services\Loans\ListLoansService;
use App\Application\Services\Loans\ReturnItemService;
use App\Domain\Entities\Book;
use App\Domain\Entities\Member;
use App\Domain\Entities\User;
use App\Domain\Exceptions\BookNotAvailableException;
use App\Domain\Exceptions\DuplicateBookInLoanException;
use App\Domain\Exceptions\ItemAlreadyReturnedException;
use App\Domain\Exceptions\MemberHasOverdueException;
use App\Domain\Exceptions\MemberLimitExceededException;
use App\Domain\Exceptions\ResourceNotFoundException;
use App\Domain\ValueObjects\Isbn;
use App\Domain\ValueObjects\LoanStatus;
use App\Domain\ValueObjects\UserRole;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\FakeClock;
use Tests\Fakes\InMemoryBookRepository;
use Tests\Fakes\InMemoryCategoryRepository;
use Tests\Fakes\InMemoryLoanRepository;
use Tests\Fakes\InMemoryMemberRepository;
use Tests\Fakes\InMemoryUserRepository;

final class LoansServicesTest extends TestCase
{
    private InMemoryLoanRepository $loanRepository;
    private InMemoryBookRepository $bookRepository;
    private InMemoryMemberRepository $memberRepository;
    private InMemoryUserRepository $userRepository;
    private InMemoryCategoryRepository $categoryRepository;
    private FakeClock $clock;

    private LendBooksService $lendBooksService;
    private ReturnItemService $returnItemService;
    private GetLoanService $getLoanService;
    private ListLoansService $listLoansService;

    private string $categoryId = '11111111-1111-4111-8111-111111111111'; // Novela
    private DateTimeZone $utc;

    protected function setUp(): void
    {
        $this->utc = new DateTimeZone('UTC');
        $this->clock = new FakeClock(new DateTimeImmutable('2026-10-01 10:00:00', $this->utc));

        $this->loanRepository = new InMemoryLoanRepository();
        $this->bookRepository = new InMemoryBookRepository();
        $this->memberRepository = new InMemoryMemberRepository();
        $this->userRepository = new InMemoryUserRepository();
        $this->categoryRepository = new InMemoryCategoryRepository();

        $this->lendBooksService = new LendBooksService(
            $this->loanRepository,
            $this->bookRepository,
            $this->memberRepository,
            $this->userRepository,
            $this->categoryRepository,
            $this->clock
        );

        $this->returnItemService = new ReturnItemService(
            $this->loanRepository,
            $this->bookRepository,
            $this->clock
        );

        $this->getLoanService = new GetLoanService($this->loanRepository);
        $this->listLoansService = new ListLoansService($this->loanRepository);

        // Sembrar usuario operador y socio base
        $this->userRepository->save(new User('u-1', 'librarian1', 'hash', UserRole::LIBRARIAN));
        $this->memberRepository->save(new Member('m-1', 'Socio Uno', '1001'));
    }

    private function createBook(string $id, string $title, int $total = 3, int $available = 3): Book
    {
        $book = new Book(
            id: $id,
            title: $title,
            author: 'Autor',
            isbn: new Isbn('978' . str_pad($id, 10, '0', STR_PAD_LEFT)),
            categoryId: $this->categoryId,
            totalCopies: $total,
            availableCopies: $available
        );
        $this->bookRepository->save($book);

        return $book;
    }

    public function test_lend_books_succeeds_and_decrements_available_copies_and_freezes_data(): void
    {
        $b1 = $this->createBook('1', 'Libro A', total: 3, available: 3);
        $b2 = $this->createBook('2', 'Libro B', total: 2, available: 2);

        $loan = $this->lendBooksService->execute(
            loanId: 'l-1',
            memberId: 'm-1',
            loanedByUserId: 'u-1',
            itemRequests: [
                ['id' => 'it-1', 'book_id' => '1'],
                ['id' => 'it-2', 'book_id' => '2'],
            ]
        );

        // Comprobar préstamo
        $this->assertSame('l-1', $loan->id());
        $this->assertCount(2, $loan->items());

        // Comprobar decremento de disponibles
        $this->assertSame(2, $this->bookRepository->findById('1')->availableCopies());
        $this->assertSame(1, $this->bookRepository->findById('2')->availableCopies());

        // Comprobar vencimiento a 14 días
        $expectedDue = new DateTimeImmutable('2026-10-15 10:00:00', $this->utc);
        $this->assertEquals($expectedDue, $loan->items()[0]->dueAt());

        // Comprobar valores congelados
        $this->assertSame('Libro A', $loan->items()[0]->bookTitle());
        $this->assertSame('Novela', $loan->items()[0]->categoryName());
    }

    public function test_lend_books_fails_with_duplicate_books_in_loan(): void
    {
        $this->createBook('1', 'Libro A', total: 5, available: 5);

        $this->expectException(DuplicateBookInLoanException::class);
        $this->expectExceptionMessage('No se puede repetir el mismo libro en un préstamo (ID: 1).');

        $this->lendBooksService->execute(
            loanId: 'l-1',
            memberId: 'm-1',
            loanedByUserId: 'u-1',
            itemRequests: [
                ['book_id' => '1'],
                ['book_id' => '1'],
            ]
        );
    }

    public function test_lend_books_fails_when_book_has_no_available_copies(): void
    {
        $this->createBook('1', 'Libro Agotado', total: 2, available: 0);

        $this->expectException(BookNotAvailableException::class);
        $this->expectExceptionMessage('No quedan ejemplares disponibles de «Libro Agotado».');

        $this->lendBooksService->execute(
            loanId: 'l-1',
            memberId: 'm-1',
            loanedByUserId: 'u-1',
            itemRequests: [['book_id' => '1']]
        );
    }

    public function test_lend_books_fails_when_member_would_exceed_limit_of_three_active_books(): void
    {
        $this->createBook('1', 'Libro 1');
        $this->createBook('2', 'Libro 2');
        $this->createBook('3', 'Libro 3');
        $this->createBook('4', 'Libro 4');

        // Primer préstamo: 2 libros (ok)
        $this->lendBooksService->execute('l-1', 'm-1', 'u-1', [
            ['id' => 'it-1', 'book_id' => '1'],
            ['id' => 'it-2', 'book_id' => '2'],
        ]);

        // Segundo préstamo: 2 libros adicionales (2 + 2 = 4 > 3 -> 422)
        $this->expectException(MemberLimitExceededException::class);
        $this->expectExceptionMessage('El socio supera el límite de 3 libros sin devolver (tiene actualmente 2 libros).');

        $this->lendBooksService->execute('l-2', 'm-1', 'u-1', [
            ['id' => 'it-3', 'book_id' => '3'],
            ['id' => 'it-4', 'book_id' => '4'],
        ]);
    }

    public function test_lend_books_fails_when_member_has_overdue_books(): void
    {
        $this->createBook('1', 'Libro 1');
        $this->createBook('2', 'Libro 2');

        // Prestar libro el 1 de octubre
        $this->lendBooksService->execute('l-1', 'm-1', 'u-1', [
            ['id' => 'it-1', 'book_id' => '1'],
        ]);

        // Adelantar reloj al 20 de octubre (venció el 15)
        $this->clock->set(new DateTimeImmutable('2026-10-20 10:00:00', $this->utc));

        // Intento de nuevo préstamo: debe ser bloqueado por mora (409)
        $this->expectException(MemberHasOverdueException::class);
        $this->expectExceptionMessage('tiene libros vencidos pendientes de devolución.');

        $this->lendBooksService->execute('l-2', 'm-1', 'u-1', [
            ['id' => 'it-2', 'book_id' => '2'],
        ]);
    }

    public function test_return_item_succeeds_restocks_and_updates_loan_status(): void
    {
        $this->createBook('1', 'Libro 1', total: 3, available: 3);

        $loan = $this->lendBooksService->execute('l-1', 'm-1', 'u-1', [
            ['id' => 'it-1', 'book_id' => '1'],
        ]);

        $this->assertSame(2, $this->bookRepository->findById('1')->availableCopies());
        $this->assertSame(LoanStatus::OPEN, $loan->status($this->clock->now()));

        // Devolver ítem
        $this->clock->advance('+2 days');
        $returnedItem = $this->returnItemService->execute('l-1', 'it-1');

        $this->assertTrue($returnedItem->isReturned());
        $this->assertSame(3, $this->bookRepository->findById('1')->availableCopies()); // restock

        // Préstamo pasa a cerrado
        $savedLoan = $this->loanRepository->findById('l-1');
        $this->assertSame(LoanStatus::CLOSED, $savedLoan->status($this->clock->now()));
    }

    public function test_returning_item_twice_fails_with_409(): void
    {
        $this->createBook('1', 'Libro 1', total: 3, available: 3);

        $this->lendBooksService->execute('l-1', 'm-1', 'u-1', [
            ['id' => 'it-1', 'book_id' => '1'],
        ]);

        // Primera devolución
        $this->returnItemService->execute('l-1', 'it-1');

        // Segunda devolución: debe fallar con 409 ITEM_ALREADY_RETURNED
        $this->expectException(ItemAlreadyReturnedException::class);
        $this->expectExceptionMessage('La línea del libro «Libro 1» ya fue devuelta previamente.');

        $this->returnItemService->execute('l-1', 'it-1');
    }
}
