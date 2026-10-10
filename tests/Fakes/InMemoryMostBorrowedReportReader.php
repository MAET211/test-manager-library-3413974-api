<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Application\Ports\Outbound\MostBorrowedReportReaderInterface;
use App\Domain\ValueObjects\DateRange;

final class InMemoryMostBorrowedReportReader implements MostBorrowedReportReaderInterface
{
    /**
     * @var array<int, array{book_id: string, book_title: string, category_name: string, loaned_at: \DateTimeImmutable}>
     */
    private array $loanEntries = [];

    public function addLoanEntry(
        string $bookId,
        string $bookTitle,
        string $categoryName,
        \DateTimeImmutable $loanedAt
    ): void {
        $this->loanEntries[] = [
            'book_id' => $bookId,
            'book_title' => $bookTitle,
            'category_name' => $categoryName,
            'loaned_at' => $loanedAt,
        ];
    }

    public function getMostBorrowed(DateRange $range): array
    {
        $filtered = array_filter($this->loanEntries, static function ($entry) use ($range) {
            return $entry['loaned_at'] >= $range->from() && $entry['loaned_at'] <= $range->to();
        });

        // Agrupar por book_id + book_title + category_name (ADR-004)
        $grouped = [];
        foreach ($filtered as $entry) {
            $key = $entry['book_id'] . '|' . $entry['book_title'] . '|' . $entry['category_name'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'book_id' => $entry['book_id'],
                    'book_title' => $entry['book_title'],
                    'category_name' => $entry['category_name'],
                    'times_borrowed' => 0,
                ];
            }
            $grouped[$key]['times_borrowed']++;
        }

        return array_values($grouped);
    }
}
