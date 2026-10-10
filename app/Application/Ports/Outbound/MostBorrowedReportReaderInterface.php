<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Domain\ValueObjects\DateRange;

interface MostBorrowedReportReaderInterface
{
    /**
     * @return array<int, array{book_id: string, book_title: string, category_name: string, times_borrowed: int}>
     */
    public function getMostBorrowed(DateRange $range): array;
}
