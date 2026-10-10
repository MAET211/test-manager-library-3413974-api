<?php

declare(strict_types=1);

namespace App\Application\Services\Books;

use App\Application\Ports\Outbound\BookRepositoryInterface;
use App\Application\Ports\Outbound\CoverStorageInterface;
use App\Domain\Exceptions\ResourceNotFoundException;

final readonly class DeleteCoverService
{
    public function __construct(
        private BookRepositoryInterface $bookRepository,
        private CoverStorageInterface $coverStorage
    ) {
    }

    public function execute(string $bookId): void
    {
        $book = $this->bookRepository->findById($bookId);

        if ($book === null || $book->isDeleted()) {
            throw new ResourceNotFoundException(
                message: "El libro con ID «{$bookId}» no existe o ha sido dado de baja.",
                details: [['field' => 'id', 'issue' => 'libro no encontrado']]
            );
        }

        $coverKey = $book->coverKey();

        // data-model §7: Retención obligatoria: primero se anula en BD y luego se borra el binario
        $book->removeCover();
        $this->bookRepository->save($book);

        if ($coverKey !== null) {
            $this->coverStorage->delete($coverKey);
        }
    }
}
