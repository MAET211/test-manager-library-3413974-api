<?php

declare(strict_types=1);

namespace App\Application\Services\Books;

use App\Application\Ports\Outbound\BookRepositoryInterface;
use App\Domain\Entities\Book;

final readonly class SearchBooksService
{
    public function __construct(
        private BookRepositoryInterface $bookRepository
    ) {
    }

    /**
     * @return array{items: array<int, Book>, page: int, size: int, total: int}
     */
    public function execute(?string $query = null, ?string $categoryId = null, int $page = 1, int $size = 20): array
    {
        $safePage = max(1, $page);
        $safeSize = min(50, max(1, $size)); // máx. 50 según contrato

        $result = $this->bookRepository->search($query, $categoryId, $safePage, $safeSize);

        return [
            'items' => $result['items'],
            'page' => $safePage,
            'size' => $safeSize,
            'total' => $result['total'],
        ];
    }
}
