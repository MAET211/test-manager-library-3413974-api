<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mappers;

use App\Domain\Entities\Member;
use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;

final class MemberMapper
{
    public static function toDomain(MemberModel $model): Member
    {
        return new Member(
            id: (string) $model->id,
            fullName: (string) $model->full_name,
            documentNumber: (string) $model->document_number
        );
    }

    public static function toModel(Member $domain, ?MemberModel $model = null): MemberModel
    {
        $model = $model ?? new MemberModel();

        $model->id = $domain->id();
        $model->full_name = $domain->fullName();
        $model->document_number = $domain->documentNumber();

        return $model;
    }
}
