<?php

declare(strict_types=1);

namespace App\Application\Services\Loans;

use App\Application\Ports\Outbound\BookRepositoryInterface;
use App\Application\Ports\Outbound\CategoryRepositoryInterface;
use App\Application\Ports\Outbound\ClockInterface;
use App\Application\Ports\Outbound\LoanRepositoryInterface;
use App\Application\Ports\Outbound\MemberRepositoryInterface;
use App\Application\Ports\Outbound\UserRepositoryInterface;
use App\Domain\Entities\Loan;
use App\Domain\Entities\LoanItem;
use App\Domain\Exceptions\DuplicateBookInLoanException;
use App\Domain\Exceptions\ResourceNotFoundException;
use App\Domain\Exceptions\ValidationException;

final readonly class LendBooksService
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private BookRepositoryInterface $bookRepository,
        private MemberRepositoryInterface $memberRepository,
        private UserRepositoryInterface $userRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private ClockInterface $clock
    ) {
    }

    /**
     * @param array<int, array{id?: string, book_id: string}> $itemRequests
     */
    public function execute(
        string $loanId,
        string $memberId,
        string $loanedByUserId,
        array $itemRequests
    ): Loan {
        $now = $this->clock->now();

        // 1. Validar socio
        $member = $this->memberRepository->findById($memberId);
        if ($member === null) {
            throw new ResourceNotFoundException(
                message: "El socio con ID «{$memberId}» no existe.",
                details: [['field' => 'member_id', 'issue' => 'socio no encontrado']]
            );
        }

        // 2. Validar usuario operador
        $user = $this->userRepository->findById($loanedByUserId);
        if ($user === null) {
            throw new ResourceNotFoundException(
                message: "El usuario operador con ID «{$loanedByUserId}» no existe.",
                details: [['field' => 'loaned_by_user_id', 'issue' => 'usuario no encontrado']]
            );
        }

        // 3. Validar cantidad de libros (1 a 3, CA-06.1)
        $itemsCount = count($itemRequests);
        if ($itemsCount < 1 || $itemsCount > 3) {
            throw new ValidationException(
                message: "Un préstamo debe contener entre 1 y 3 libros (se recibieron {$itemsCount}).",
                details: [['field' => 'items', 'issue' => 'debe contener entre 1 y 3 libros']]
            );
        }

        // 4. Validar libros duplicados en el préstamo (CA-06.2)
        $bookIds = [];
        foreach ($itemRequests as $idx => $req) {
            $bookId = $req['book_id'] ?? '';
            if ($bookId === '') {
                throw new ValidationException(
                    message: "El campo book_id es requerido en el ítem {$idx}.",
                    details: [['field' => "items[{$idx}].book_id", 'issue' => 'book_id requerido']]
                );
            }
            if (in_array($bookId, $bookIds, true)) {
                throw new DuplicateBookInLoanException(
                    message: "No se puede repetir el mismo libro en un préstamo (ID: {$bookId}).",
                    details: [['field' => "items[{$idx}].book_id", 'issue' => 'libro repetido en el préstamo']]
                );
            }
            $bookIds[] = $bookId;
        }

        // 5. Validar mora y límite de libros activos del socio (CA-06.4 y CA-06.5)
        $unreturnedItems = $this->loanRepository->findUnreturnedItemsByMemberId($memberId);
        $hasOverdue = false;
        foreach ($unreturnedItems as $unreturned) {
            if ($unreturned->isOverdue($now)) {
                $hasOverdue = true;
                break;
            }
        }

        $member->canBorrow(
            currentActiveLoansCount: count($unreturnedItems),
            newBooksCount: $itemsCount,
            hasOverdue: $hasOverdue
        );

        // 6. Obtener libros por lote (Q3)
        $booksMap = $this->bookRepository->findByIds($bookIds);
        foreach ($bookIds as $bookId) {
            if (!isset($booksMap[$bookId]) || $booksMap[$bookId]->isDeleted()) {
                throw new ResourceNotFoundException(
                    message: "El libro con ID «{$bookId}» no existe o está dado de baja.",
                    details: [['field' => 'book_id', 'issue' => 'libro no encontrado']]
                );
            }
        }

        // 7. Cargar categorías para congelar el nombre de categoría
        $categoriesMap = [];
        foreach ($this->categoryRepository->findAll() as $cat) {
            $categoriesMap[$cat->id()] = $cat->name();
        }

        // 8. Descontar ejemplares y generar LoanItems con datos congelados (CA-06.3 / CA-06.6)
        $loanItems = [];
        $dueAt = $now->modify('+14 days');

        foreach ($itemRequests as $idx => $req) {
            $bookId = $req['book_id'];
            $book = $booksMap[$bookId];

            // Descontar ejemplar (lanza BookNotAvailableException 409 si available_copies <= 0)
            $book->withdraw();

            $categoryName = $categoriesMap[$book->categoryId()] ?? 'General';
            $itemId = $req['id'] ?? vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));

            $loanItems[] = new LoanItem(
                id: $itemId,
                loanId: $loanId,
                bookId: $book->id(),
                bookTitle: $book->title(),
                isbn: $book->isbn()->value(),
                categoryName: $categoryName,
                dueAt: $dueAt
            );
        }

        // 9. Construir y persistir el préstamo y los libros actualizados
        $loan = new Loan(
            id: $loanId,
            memberId: $memberId,
            loanedByUserId: $loanedByUserId,
            loanedAt: $now,
            items: $loanItems
        );

        // Guardar libros (ejemplares descontados)
        foreach ($booksMap as $book) {
            $this->bookRepository->save($book);
        }

        // Guardar préstamo
        $this->loanRepository->save($loan);

        return $loan;
    }
}
