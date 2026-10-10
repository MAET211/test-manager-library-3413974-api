<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Application\Ports\Outbound\MemberRepositoryInterface;
use App\Domain\Entities\Member;

final class InMemoryMemberRepository implements MemberRepositoryInterface
{
    /**
     * @var array<string, Member> Indexado por id
     */
    private array $members = [];

    public function save(Member $member): void
    {
        $this->members[$member->id()] = $member;
    }

    public function findById(string $id): ?Member
    {
        return $this->members[$id] ?? null;
    }

    public function findByDocumentNumber(string $documentNumber): ?Member
    {
        $trimmed = trim($documentNumber);

        foreach ($this->members as $member) {
            if ($member->documentNumber() === $trimmed) {
                return $member;
            }
        }

        return null;
    }

    public function search(?string $query, int $page = 1, int $size = 20): array
    {
        $filtered = array_values($this->members);

        if ($query !== null && trim($query) !== '') {
            $q = mb_strtolower(trim($query));
            $filtered = array_filter($filtered, static function (Member $m) use ($q) {
                return str_contains(mb_strtolower($m->fullName()), $q)
                    || str_contains(mb_strtolower($m->documentNumber()), $q);
            });
            $filtered = array_values($filtered);
        }

        $total = count($filtered);
        $offset = ($page - 1) * $size;
        $items = array_slice($filtered, $offset, $size);

        return [
            'items' => $items,
            'total' => $total,
        ];
    }
}
