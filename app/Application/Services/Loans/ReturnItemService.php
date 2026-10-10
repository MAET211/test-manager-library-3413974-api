<?php

declare(strict_types=1);

namespace App\Application\Services\Loans;

use App\Application\Ports\Outbound\BookRepositoryInterface;
use App\Application\Ports\Outbound\ClockInterface;
use App\Application\Ports\Outbound\LoanRepositoryInterface;
use App\Domain\Entities\LoanItem;
use App\Domain\Exceptions\ResourceNotFoundException;

final readonly class ReturnItemService
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private BookRepositoryInterface $bookRepository,
        private ClockInterface $clock
    ) {
    }

    public function execute(string $loanId, string $itemId): LoanItem
    {
        $now = $this->clock->now();

        $loan = $this->loanRepository->findById($loanId);
        if ($loan === null) {
            throw new ResourceNotFoundException(
                message: "El préstamo con ID «{$loanId}» no existe.",
                details: [['field' => 'loan_id', 'issue' => 'préstamo no encontrado']]
            );
        }

        $item = $loan->findItem($itemId);
        if ($item === null) {
            throw new ResourceNotFoundException(
                message: "La línea con ID «{$itemId}» no pertenece al préstamo «{$loanId}».",
                details: [['field' => 'item_id', 'issue' => 'línea de préstamo no encontrada']]
            );
        }

        $book = $this->bookRepository->findById($item->bookId());
        if ($book === null) {
            throw new ResourceNotFoundException(
                message: "El libro asociado «{$item->bookId()}» no existe.",
                details: [['field' => 'book_id', 'issue' => 'libro no encontrado']]
            );
        }

        // 1. Devolver la línea (lanza ItemAlreadyReturnedException 409 si ya estaba devuelta, CA-07.2)
        $loan->returnItem($itemId, $now);

        // 2. Sumar 1 a disponibles del libro (CA-07.1)
        $book->restock();

        // 3. Persistir ambos cambios
        $this->bookRepository->save($book);
        $this->loanRepository->save($loan);

        return $item;
    }
}
