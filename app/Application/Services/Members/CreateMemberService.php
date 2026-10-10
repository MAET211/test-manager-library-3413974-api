<?php

declare(strict_types=1);

namespace App\Application\Services\Members;

use App\Application\Ports\Outbound\MemberRepositoryInterface;
use App\Domain\Entities\Member;
use App\Domain\Exceptions\DocumentTakenException;

final readonly class CreateMemberService
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository
    ) {
    }

    public function execute(string $id, string $fullName, string $documentNumber): Member
    {
        $trimmedDoc = trim($documentNumber);

        // CA-05.1: El documento es único: repetido 409
        if ($this->memberRepository->findByDocumentNumber($trimmedDoc) !== null) {
            throw new DocumentTakenException(
                documentNumber: $trimmedDoc,
                details: [['field' => 'document_number', 'issue' => 'número de documento ya registrado']]
            );
        }

        $member = new Member($id, $fullName, $trimmedDoc);
        $this->memberRepository->save($member);

        return $member;
    }
}
