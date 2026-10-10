<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Reports;

use App\Application\Ports\Outbound\MostBorrowedReportReaderInterface;
use App\Domain\ValueObjects\DateRange;
use Illuminate\Support\Facades\DB;

final class SqlMostBorrowedReportReader implements MostBorrowedReportReaderInterface
{
    /**
     * @return array<int, array{book_id: string, book_title: string, category_name: string, times_borrowed: int}>
     */
    public function getMostBorrowed(DateRange $range): array
    {
        // Ambos inclusive: from al inicio del día, to al final del día
        $fromStr = $range->from()->setTime(0, 0, 0)->format('Y-m-d H:i:sP');
        $toStr = $range->to()->setTime(23, 59, 59)->format('Y-m-d H:i:sP');

        // ADR-004: Agrupa por valores congelados tal como quedaron en loan_item
        $rows = DB::select("
            SELECT
                li.book_id::text,
                li.book_title,
                li.category_name,
                COUNT(*)::int AS times_borrowed
            FROM library.loan_item li
            JOIN library.loan l ON l.id = li.loan_id
            WHERE l.loaned_at >= ? AND l.loaned_at <= ?
            GROUP BY li.book_id, li.book_title, li.category_name
            ORDER BY times_borrowed DESC, li.book_title ASC
        ", [$fromStr, $toStr]);

        return array_map(static fn ($r) => [
            'book_id' => (string) $r->book_id,
            'book_title' => (string) $r->book_title,
            'category_name' => (string) $r->category_name,
            'times_borrowed' => (int) $r->times_borrowed,
        ], $rows);
    }
}
