<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Application\Ports\Outbound\LoanRepositoryInterface;
use App\Domain\Entities\Loan;
use App\Domain\Entities\LoanItem;
use DateTimeImmutable;
use DateTimeZone;

final class InMemoryLoanRepository implements LoanRepositoryInterface
{
    /**
     * @var array<string, Loan> Indexado por id
     */
    private array $loans = [];

    /**
     * @var array<string, string> memberId => memberName para visualización en vencidos
     */
    private array $memberNamesMap = [];

    public function setMemberName(string $memberId, string $memberName): void
    {
        $this->memberNamesMap[$memberId] = $memberName;
    }

    public function save(Loan $loan): void
    {
        $this->loans[$loan->id()] = $loan;
    }

    public function findById(string $id): ?Loan
    {
        return $this->loans[$id] ?? null;
    }

    public function list(
        ?DateTimeImmutable $from,
        ?DateTimeImmutable $to,
        ?string $status,
        ?string $memberId,
        int $page = 1,
        int $size = 20
    ): array {
        $filtered = array_values($this->loans);

        if ($memberId !== null && trim($memberId) !== '') {
            $filtered = array_filter($filtered, static fn (Loan $l) => $l->memberId() === $memberId);
            $filtered = array_values($filtered);
        }

        if ($from !== null) {
            $filtered = array_filter($filtered, static fn (Loan $l) => $l->loanedAt() >= $from);
            $filtered = array_values($filtered);
        }

        if ($to !== null) {
            $filtered = array_filter($filtered, static fn (Loan $l) => $l->loanedAt() <= $to);
            $filtered = array_values($filtered);
        }

        if ($status !== null && trim($status) !== '') {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $filtered = array_filter($filtered, static fn (Loan $l) => $l->status($now)->value === $status);
            $filtered = array_values($filtered);
        }

        // Orden descendente por loaned_at (CA-08.2)
        usort($filtered, static fn (Loan $a, Loan $b) => $b->loanedAt() <=> $a->loanedAt());

        $total = count($filtered);
        $offset = ($page - 1) * $size;
        $items = array_slice($filtered, $offset, $size);

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function findUnreturnedItemsByMemberId(string $memberId): array
    {
        $unreturned = [];

        foreach ($this->loans as $loan) {
            if ($loan->memberId() === $memberId) {
                foreach ($loan->items() as $item) {
                    if (!$item->isReturned()) {
                        $unreturned[] = $item;
                    }
                }
            }
        }

        return $unreturned;
    }

    public function findOverdueItems(?DateTimeImmutable $asOf = null): array
    {
        $now = $asOf ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $overdue = [];

        foreach ($this->loans as $loan) {
            $memberName = $this->memberNamesMap[$loan->memberId()] ?? 'Socio Anónimo';

            foreach ($loan->items() as $item) {
                if ($item->isOverdue($now)) {
                    $overdue[] = [
                        'loan_id' => $loan->id(),
                        'item_id' => $item->id(),
                        'book_title' => $item->bookTitle(),
                        'member_name' => $memberName,
                        'due_at' => $item->dueAt(),
                        'days_late' => $item->daysLate($now),
                    ];
                }
            }
        }

        return $overdue;
    }
}
