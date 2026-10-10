<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Domain\Entities\Book;
use App\Domain\ValueObjects\Isbn;

interface BookRepositoryInterface
{
    public function save(Book $book): void;

    public function findById(string $id): ?Book;

    public function findByIsbn(Isbn $isbn, bool $activeOnly = true): ?Book;

    /**
     * @param array<int, string> $ids
     * @return array<string, Book> Mapa de id => Book
     */
    public function findByIds(array $ids): array;

    /**
     * @return array{items: array<int, Book>, total: int}
     */
    public function search(?string $query, ?string $categoryId, int $page = 1, int $size = 20): array;

    public function hasActiveLoans(string $bookId): bool;
}
