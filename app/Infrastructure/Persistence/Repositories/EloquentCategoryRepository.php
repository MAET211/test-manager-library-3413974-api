<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repositories;

use App\Application\Ports\Outbound\CategoryRepositoryInterface;
use App\Domain\Entities\Category;
use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use App\Infrastructure\Persistence\Mappers\CategoryMapper;

final class EloquentCategoryRepository implements CategoryRepositoryInterface
{
    public function findById(string $id): ?Category
    {
        $model = CategoryModel::find($id);

        return $model !== null ? CategoryMapper::toDomain($model) : null;
    }

    public function findAll(): array
    {
        $models = CategoryModel::orderBy('name')->get();

        return $models->map(fn (CategoryModel $m) => CategoryMapper::toDomain($m))->all();
    }
}
