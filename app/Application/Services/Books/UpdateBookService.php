<?php

declare(strict_types=1);

namespace App\Application\Services\Books;

use App\Application\Ports\Outbound\BookRepositoryInterface;
use App\Application\Ports\Outbound\CategoryRepositoryInterface;
use App\Domain\Entities\Book;
use App\Domain\Exceptions\ResourceNotFoundException;

final readonly class UpdateBookService
{
    public function __construct(
        private BookRepositoryInterface $bookRepository,
        private CategoryRepositoryInterface $categoryRepository
    ) {
    }

    public function execute(
        string $id,
        string $title,
        string $author,
        string $categoryId,
        int $totalCopies
    ): Book {
        $book = $this->bookRepository->findById($id);

        if ($book === null || $book->isDeleted()) {
            throw new ResourceNotFoundException(
                message: "El libro con ID «{$id}» no existe o ha sido dado de baja.",
                details: [['field' => 'id', 'issue' => 'libro no encontrado']]
            );
        }

        if ($this->categoryRepository->findById($categoryId) === null) {
            throw new ResourceNotFoundException(
                message: "La categoría con ID «{$categoryId}» no existe.",
                details: [['field' => 'category_id', 'issue' => 'categoría no encontrada']]
            );
        }

        // CA-03.3: Ajusta disponibles por diferencia y valida que no quede < 0
        $book->updateDetails($title, $author, $categoryId, $totalCopies);
        $this->bookRepository->save($book);

        return $book;
    }
}
