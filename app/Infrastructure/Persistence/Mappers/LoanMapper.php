<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mappers;

use App\Domain\Entities\Loan;
use App\Infrastructure\Persistence\Eloquent\Models\LoanModel;
use DateTimeImmutable;
use DateTimeZone;

final class LoanMapper
{
    public static function toDomain(LoanModel $model): Loan
    {
        $utc = new DateTimeZone('UTC');
        $loanedAt = DateTimeImmutable::createFromInterface($model->loaned_at)->setTimezone($utc);

        $items = [];
        foreach ($model->items as $itemModel) {
            $items[] = LoanItemMapper::toDomain($itemModel);
        }

        return new Loan(
            id: (string) $model->id,
            memberId: (string) $model->member_id,
            loanedByUserId: (string) $model->loaned_by_user_id,
            loanedAt: $loanedAt,
            items: $items
        );
    }

    public static function toModel(Loan $domain, ?LoanModel $model = null): LoanModel
    {
        $model = $model ?? new LoanModel();

        $model->id = $domain->id();
        $model->member_id = $domain->memberId();
        $model->loaned_by_user_id = $domain->loanedByUserId();
        $model->loaned_at = $domain->loanedAt()->format('Y-m-d H:i:sP');

        return $model;
    }
}
