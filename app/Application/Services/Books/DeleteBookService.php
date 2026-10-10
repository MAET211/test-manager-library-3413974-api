<?php

declare(strict_types=1);

namespace App\Application\Services\Books;

use App\Application\Ports\Outbound\BookRepositoryInterface;
use App\Domain\Exceptions\ResourceNotFoundException;

final readonly class DeleteBookService
{
    public function __construct(
        private BookRepositoryInterface $bookRepository
    ) {
    }

    public function execute(string $id): void
    {
        $book = $this->bookRepository->findById($id);

        if ($book === null || $book->isDeleted()) {
            throw new ResourceNotFoundException(
                message: "El libro con ID «{$id}» no existe o ya ha sido dado de baja.",
                details: [['field' => 'id', 'issue' => 'libro no encontrado']]
            );
        }

        // CA-03.5: Comprobar préstamos abiertos. Si tiene, lanza 409
        $hasOpenLoans = $this->bookRepository->hasActiveLoans($id);
        $book->softDelete($hasOpenLoans);

        $this->bookRepository->save($book);
    }
}
