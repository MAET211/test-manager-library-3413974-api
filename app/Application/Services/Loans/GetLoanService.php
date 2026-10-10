<?php

declare(strict_types=1);

namespace App\Application\Services\Loans;

use App\Application\Ports\Outbound\LoanRepositoryInterface;
use App\Domain\Entities\Loan;
use App\Domain\Exceptions\ResourceNotFoundException;

final readonly class GetLoanService
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository
    ) {
    }

    public function execute(string $id): Loan
    {
        $loan = $this->loanRepository->findById($id);

        if ($loan === null) {
            throw new ResourceNotFoundException(
                message: "El préstamo con ID «{$id}» no existe.",
                details: [['field' => 'id', 'issue' => 'préstamo no encontrado']]
            );
        }

        return $loan;
    }
}
