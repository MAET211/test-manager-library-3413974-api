<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mappers;

use App\Domain\Entities\Book;
use App\Domain\ValueObjects\Isbn;
use App\Infrastructure\Persistence\Eloquent\Models\BookModel;
use DateTimeImmutable;
use DateTimeZone;

final class BookMapper
{
    public static function toDomain(BookModel $model): Book
    {
        $deletedAt = null;
        if ($model->deleted_at !== null) {
            $deletedAt = DateTimeImmutable::createFromInterface($model->deleted_at)
                ->setTimezone(new DateTimeZone('UTC'));
        }

        return new Book(
            id: (string) $model->id,
            title: (string) $model->title,
            author: (string) $model->author,
            isbn: new Isbn((string) $model->isbn),
            categoryId: (string) $model->category_id,
            totalCopies: (int) $model->total_copies,
            availableCopies: (int) $model->available_copies,
            coverKey: $model->cover_key ? (string) $model->cover_key : null,
            deletedAt: $deletedAt
        );
    }

    public static function toModel(Book $domain, ?BookModel $model = null): BookModel
    {
        $model = $model ?? new BookModel();

        $model->id = $domain->id();
        $model->title = $domain->title();
        $model->author = $domain->author();
        $model->isbn = $domain->isbn()->value();
        $model->category_id = $domain->categoryId();
        $model->total_copies = $domain->totalCopies();
        $model->available_copies = $domain->availableCopies();
        $model->cover_key = $domain->coverKey();
        $model->deleted_at = $domain->deletedAt()?->format('Y-m-d H:i:sP');

        return $model;
    }
}
