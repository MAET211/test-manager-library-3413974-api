<?php

declare(strict_types=1);

namespace App\Application\Services\Loans;

use App\Application\Ports\Outbound\LoanRepositoryInterface;
use App\Domain\Entities\Loan;
use DateTimeImmutable;

final readonly class ListLoansService
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository
    ) {
    }

    /**
     * @return array{items: array<int, Loan>, page: int, size: int, total: int}
     */
    public function execute(
        ?DateTimeImmutable $from = null,
        ?DateTimeImmutable $to = null,
        ?string $status = null,
        ?string $memberId = null,
        int $page = 1,
        int $size = 20
    ): array {
        $safePage = max(1, $page);
        $safeSize = min(50, max(1, $size));

        $result = $this->loanRepository->list($from, $to, $status, $memberId, $safePage, $safeSize);

        return [
            'items' => $result['items'],
            'page' => $safePage,
            'size' => $safeSize,
            'total' => $result['total'],
        ];
    }
}
