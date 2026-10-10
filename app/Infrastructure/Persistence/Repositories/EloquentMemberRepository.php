<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repositories;

use App\Application\Ports\Outbound\MemberRepositoryInterface;
use App\Domain\Entities\Member;
use App\Domain\Exceptions\DocumentTakenException;
use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Infrastructure\Persistence\Mappers\MemberMapper;
use Illuminate\Database\QueryException;

final class EloquentMemberRepository implements MemberRepositoryInterface
{
    public function save(Member $member): void
    {
        try {
            $model = MemberModel::find($member->id());
            $model = MemberMapper::toModel($member, $model);
            $model->save();
        } catch (QueryException $e) {
            if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'uq_member_document')) {
                throw new DocumentTakenException($member->documentNumber());
            }
            throw $e;
        }
    }

    public function findById(string $id): ?Member
    {
        $model = MemberModel::find($id);

        return $model !== null ? MemberMapper::toDomain($model) : null;
    }

    public function findByDocumentNumber(string $documentNumber): ?Member
    {
        $model = MemberModel::where('document_number', trim($documentNumber))->first();

        return $model !== null ? MemberMapper::toDomain($model) : null;
    }

    public function search(?string $query, int $page = 1, int $size = 20): array
    {
        $builder = MemberModel::query();

        if ($query !== null && trim($query) !== '') {
            $term = '%' . trim($query) . '%';
            $builder->where(function ($q) use ($term) {
                $q->where('full_name', 'ILIKE', $term)
                  ->orWhere('document_number', 'ILIKE', $term);
            });
        }

        $total = $builder->count();

        $models = $builder->orderBy('full_name')
            ->forPage($page, $size)
            ->get();

        return [
            'items' => $models->map(fn (MemberModel $m) => MemberMapper::toDomain($m))->all(),
            'total' => $total,
        ];
    }
}
