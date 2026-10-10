<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repositories;

use App\Application\Ports\Outbound\BookRepositoryInterface;
use App\Domain\Entities\Book;
use App\Domain\Exceptions\BookNotAvailableException;
use App\Domain\Exceptions\IsbnTakenException;
use App\Domain\ValueObjects\Isbn;
use App\Infrastructure\Persistence\Eloquent\Models\BookModel;
use App\Infrastructure\Persistence\Eloquent\Models\LoanItemModel;
use App\Infrastructure\Persistence\Mappers\BookMapper;
use Illuminate\Database\QueryException;

final class EloquentBookRepository implements BookRepositoryInterface
{
    public function save(Book $book): void
    {
        try {
            $model = BookModel::find($book->id());
            $model = BookMapper::toModel($book, $model);
            $model->save();
        } catch (QueryException $e) {
            // ADR-002: ck_book_available_range salta ante conflicto concurrente o existencias negativas
            if ($e->getCode() === '23514' || str_contains($e->getMessage(), 'ck_book_available_range')) {
                throw new BookNotAvailableException($book->title());
            }

            // CA-03.2: ISBN único entre libros activos
            if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'uq_book_isbn_active')) {
                throw new IsbnTakenException($book->isbn()->value());
            }

            throw $e;
        }
    }

    public function findById(string $id): ?Book
    {
        $model = BookModel::find($id);

        return $model !== null ? BookMapper::toDomain($model) : null;
    }

    public function findByIsbn(Isbn $isbn, bool $activeOnly = true): ?Book
    {
        $query = BookModel::where('isbn', $isbn->value());

        if ($activeOnly) {
            $query->whereNull('deleted_at');
        }

        $model = $query->first();

        return $model !== null ? BookMapper::toDomain($model) : null;
    }

    public function findByIds(array $ids): array
    {
        $models = BookModel::whereIn('id', $ids)->get();

        $result = [];
        foreach ($models as $m) {
            $domain = BookMapper::toDomain($m);
            $result[$domain->id()] = $domain;
        }

        return $result;
    }

    public function search(?string $query, ?string $categoryId, int $page = 1, int $size = 20): array
    {
        // CA-04.1: Solo libros activos
        $builder = BookModel::whereNull('deleted_at');

        if ($categoryId !== null && trim($categoryId) !== '') {
            $builder->where('category_id', $categoryId);
        }

        if ($query !== null && trim($query) !== '') {
            $term = '%' . trim($query) . '%';
            $builder->where(function ($q) use ($term) {
                $q->where('title', 'ILIKE', $term)
                  ->orWhere('author', 'ILIKE', $term);
            });
        }

        $total = $builder->count();

        // CA-04.1: Orden por título, paginada
        $models = $builder->orderBy('title')
            ->forPage($page, $size)
            ->get();

        return [
            'items' => $models->map(fn (BookModel $m) => BookMapper::toDomain($m))->all(),
            'total' => $total,
        ];
    }

    public function hasActiveLoans(string $bookId): bool
    {
        return LoanItemModel::where('book_id', $bookId)
            ->whereNull('returned_at')
            ->exists();
    }
}
