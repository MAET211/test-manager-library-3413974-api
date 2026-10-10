<?php

declare(strict_types=1);

namespace App\Application\Services\Reports;

use App\Application\Ports\Outbound\MostBorrowedReportReaderInterface;
use App\Domain\ValueObjects\DateRange;

final readonly class GetMostBorrowedReportService
{
    public function __construct(
        private MostBorrowedReportReaderInterface $reportReader
    ) {
    }

    /**
     * @return array{from: string, to: string, rows: array<int, array{book_id: string, book_title: string, category_name: string, times_borrowed: int}>}
     */
    public function execute(string $fromDate, string $toDate): array
    {
        // CA-10.2: Fin anterior al inicio -> 422 INVALID_DATE_RANGE
        $dateRange = DateRange::fromStrings($fromDate, $toDate);

        $rows = $this->reportReader->getMostBorrowed($dateRange);

        // CA-10.1: Ordenado de mayor a menor cantidad de veces prestado, luego por título
        usort($rows, static function ($a, $b) {
            $cmp = $b['times_borrowed'] <=> $a['times_borrowed'];
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp($a['book_title'], $b['book_title']);
        });

        return [
            'from' => $dateRange->fromDateString(),
            'to' => $dateRange->toDateString(),
            'rows' => $rows,
        ];
    }
}
