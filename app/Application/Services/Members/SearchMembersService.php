<?php

declare(strict_types=1);

namespace App\Application\Services\Members;

use App\Application\Ports\Outbound\MemberRepositoryInterface;
use App\Domain\Entities\Member;

final readonly class SearchMembersService
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository
    ) {
    }

    /**
     * @return array{items: array<int, Member>, page: int, size: int, total: int}
     */
    public function execute(?string $query = null, int $page = 1, int $size = 20): array
    {
        $safePage = max(1, $page);
        $safeSize = min(50, max(1, $size));

        $result = $this->memberRepository->search($query, $safePage, $safeSize);

        return [
            'items' => $result['items'],
            'page' => $safePage,
            'size' => $safeSize,
            'total' => $result['total'],
        ];
    }
}
