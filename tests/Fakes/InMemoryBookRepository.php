<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Application\Ports\Outbound\BookRepositoryInterface;
use App\Domain\Entities\Book;
use App\Domain\ValueObjects\Isbn;

final class InMemoryBookRepository implements BookRepositoryInterface
{
    /**
     * @var array<string, Book> Indexado por id
     */
    private array $books = [];

    /**
     * @var array<string, bool> Map de bookId => hasActiveLoans
     */
    private array $activeLoansMap = [];

    public function save(Book $book): void
    {
        $this->books[$book->id()] = $book;
    }

    public function findById(string $id): ?Book
    {
        return $this->books[$id] ?? null;
    }

    public function findByIsbn(Isbn $isbn, bool $activeOnly = true): ?Book
    {
        foreach ($this->books as $book) {
            if ($book->isbn()->equals($isbn)) {
                if ($activeOnly && $book->isDeleted()) {
                    continue;
                }

                return $book;
            }
        }

        return null;
    }

    public function findByIds(array $ids): array
    {
        $result = [];
        foreach ($ids as $id) {
            if (isset($this->books[$id])) {
                $result[$id] = $this->books[$id];
            }
        }

        return $result;
    }

    public function search(?string $query, ?string $categoryId, int $page = 1, int $size = 20): array
    {
        // Solo libros activos (CA-04.1)
        $filtered = array_filter($this->books, static fn (Book $b) => !$b->isDeleted());
        $filtered = array_values($filtered);

        if ($categoryId !== null && trim($categoryId) !== '') {
            $filtered = array_filter($filtered, static fn (Book $b) => $b->categoryId() === $categoryId);
            $filtered = array_values($filtered);
        }

        if ($query !== null && trim($query) !== '') {
            $q = mb_strtolower(trim($query));
            $filtered = array_filter($filtered, static function (Book $b) use ($q) {
                return str_contains(mb_strtolower($b->title()), $q)
                    || str_contains(mb_strtolower($b->author()), $q);
            });
            $filtered = array_values($filtered);
        }

        // Orden por título (CA-04.1)
        usort($filtered, static fn (Book $a, Book $b) => strcmp($a->title(), $b->title()));

        $total = count($filtered);
        $offset = ($page - 1) * $size;
        $items = array_slice($filtered, $offset, $size);

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function setActiveLoans(string $bookId, bool $hasActive): void
    {
        $this->activeLoansMap[$bookId] = $hasActive;
    }

    public function hasActiveLoans(string $bookId): bool
    {
        return $this->activeLoansMap[$bookId] ?? false;
    }
}
