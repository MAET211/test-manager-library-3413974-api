<?php

declare(strict_types=1);

namespace App\Application\Services\Books;

use App\Application\Ports\Outbound\BookRepositoryInterface;
use App\Application\Ports\Outbound\CategoryRepositoryInterface;
use App\Domain\Entities\Book;
use App\Domain\Exceptions\IsbnTakenException;
use App\Domain\Exceptions\ResourceNotFoundException;
use App\Domain\ValueObjects\Isbn;

final readonly class CreateBookService
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
        string $isbn,
        string $categoryId,
        int $totalCopies
    ): Book {
        $isbnVO = new Isbn($isbn);

        // CA-03.2: El ISBN es único entre libros activos (409)
        if ($this->bookRepository->findByIsbn($isbnVO, activeOnly: true) !== null) {
            throw new IsbnTakenException(
                isbn: $isbnVO->value(),
                details: [['field' => 'isbn', 'issue' => 'ISBN ya registrado en un libro activo']]
            );
        }

        if ($this->categoryRepository->findById($categoryId) === null) {
            throw new ResourceNotFoundException(
                message: "La categoría con ID «{$categoryId}» no existe.",
                details: [['field' => 'category_id', 'issue' => 'categoría no encontrada']]
            );
        }

        // CA-03.1: Los disponibles arrancan iguales al total
        $book = new Book(
            id: $id,
            title: $title,
            author: $author,
            isbn: $isbnVO,
            categoryId: $categoryId,
            totalCopies: $totalCopies
        );

        $this->bookRepository->save($book);

        return $book;
    }
}
