<?php

declare(strict_types=1);

namespace App\Application\Services\Loans;

use App\Application\Ports\Outbound\LoanRepositoryInterface;

final readonly class GetOverdueLoansService
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private \App\Application\Ports\Outbound\ClockInterface $clock
    ) {
    }

    /**
     * @return array<int, array{loan_id: string, item_id: string, book_title: string, member_name: string, due_at: string, days_late: int}>
     */
    public function execute(): array
    {
        $overdueRaw = $this->loanRepository->findOverdueItems($this->clock->now());

        // CA-09.2: Ordenadas por más días de retraso primero
        usort($overdueRaw, static fn ($a, $b) => $b['days_late'] <=> $a['days_late']);

        $formatted = [];
        foreach ($overdueRaw as $item) {
            $formatted[] = [
                'loan_id' => $item['loan_id'],
                'item_id' => $item['item_id'],
                'book_title' => $item['book_title'],
                'member_name' => $item['member_name'],
                'due_at' => $item['due_at']->format('Y-m-d\TH:i:s\Z'),
                'days_late' => $item['days_late'],
            ];
        }

        return $formatted;
    }
}
