<?php

declare(strict_types=1);

namespace App\Application\Services\Members;

use App\Application\Ports\Outbound\LoanRepositoryInterface;
use App\Application\Ports\Outbound\MemberRepositoryInterface;
use App\Domain\Entities\Member;
use App\Domain\Exceptions\ResourceNotFoundException;

final readonly class GetMemberService
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository,
        private LoanRepositoryInterface $loanRepository
    ) {
    }

    /**
     * @return array{member: Member, active_loans: int}
     */
    public function execute(string $id): array
    {
        $member = $this->memberRepository->findById($id);

        if ($member === null) {
            throw new ResourceNotFoundException(
                message: "El socio con ID «{$id}» no existe.",
                details: [['field' => 'id', 'issue' => 'socio no encontrado']]
            );
        }

        $unreturnedItems = $this->loanRepository->findUnreturnedItemsByMemberId($id);

        return [
            'member' => $member,
            'active_loans' => count($unreturnedItems),
        ];
    }
}
