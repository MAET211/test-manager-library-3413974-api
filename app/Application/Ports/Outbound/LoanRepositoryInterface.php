<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Domain\Entities\Loan;
use App\Domain\Entities\LoanItem;
use DateTimeImmutable;

interface LoanRepositoryInterface
{
    public function save(Loan $loan): void;

    public function findById(string $id): ?Loan;

    /**
     * @return array{items: array<int, Loan>, total: int}
     */
    public function list(
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        ?string $status,
        ?string $memberId,
        int $page = 1,
        int $size = 20
    ): array;

    /**
     * @return array<int, LoanItem>
     */
    public function findUnreturnedItemsByMemberId(string $memberId): array;

    /**
     * @return array<int, array{loan_id: string, item_id: string, book_title: string, member_name: string, due_at: DateTimeImmutable, days_late: int}>
     */
    public function findOverdueItems(?DateTimeImmutable $asOf = null): array;
}
