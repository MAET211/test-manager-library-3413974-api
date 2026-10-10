<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mappers;

use App\Domain\Entities\LoanItem;
use App\Infrastructure\Persistence\Eloquent\Models\LoanItemModel;
use DateTimeImmutable;
use DateTimeZone;

final class LoanItemMapper
{
    public static function toDomain(LoanItemModel $model): LoanItem
    {
        $utc = new DateTimeZone('UTC');
        $dueAt = DateTimeImmutable::createFromInterface($model->due_at)->setTimezone($utc);
        $returnedAt = null;

        if ($model->returned_at !== null) {
            $returnedAt = DateTimeImmutable::createFromInterface($model->returned_at)->setTimezone($utc);
        }

        return new LoanItem(
            id: (string) $model->id,
            loanId: (string) $model->loan_id,
            bookId: (string) $model->book_id,
            bookTitle: (string) $model->book_title,
            isbn: (string) $model->isbn,
            categoryName: (string) $model->category_name,
            dueAt: $dueAt,
            returnedAt: $returnedAt
        );
    }

    public static function toModel(LoanItem $domain, ?LoanItemModel $model = null): LoanItemModel
    {
        $model = $model ?? new LoanItemModel();

        $model->id = $domain->id();
        $model->loan_id = $domain->loanId();
        $model->book_id = $domain->bookId();
        $model->book_title = $domain->bookTitle();
        $model->isbn = $domain->isbn();
        $model->category_name = $domain->categoryName();
        $model->due_at = $domain->dueAt()->format('Y-m-d H:i:sP');
        $model->returned_at = $domain->returnedAt()?->format('Y-m-d H:i:sP');

        return $model;
    }
}
