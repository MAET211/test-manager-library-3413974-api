<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Domain\Entities\Member;

interface MemberRepositoryInterface
{
    public function save(Member $member): void;

    public function findById(string $id): ?Member;

    public function findByDocumentNumber(string $documentNumber): ?Member;

    /**
     * @return array{items: array<int, Member>, total: int}
     */
    public function search(?string $query, int $page = 1, int $size = 20): array;
}
