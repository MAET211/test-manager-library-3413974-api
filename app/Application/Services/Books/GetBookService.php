<?php

declare(strict_types=1);

namespace App\Application\Services\Books;

use App\Application\Ports\Outbound\BookRepositoryInterface;
use App\Domain\Entities\Book;
use App\Domain\Exceptions\ResourceNotFoundException;

final readonly class GetBookService
{
    public function __construct(
        private BookRepositoryInterface $bookRepository
    ) {
    }

    public function execute(string $id): Book
    {
        $book = $this->bookRepository->findById($id);

        if ($book === null || $book->isDeleted()) {
            throw new ResourceNotFoundException(
                message: "El libro con ID «{$id}» no existe o ha sido dado de baja.",
                details: [['field' => 'id', 'issue' => 'libro no encontrado']]
            );
        }

        return $book;
    }
}
