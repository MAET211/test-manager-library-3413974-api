<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mappers;

use App\Domain\Entities\Category;
use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;

final class CategoryMapper
{
    public static function toDomain(CategoryModel $model): Category
    {
        return new Category(
            id: (string) $model->id,
            name: (string) $model->name
        );
    }

    public static function toModel(Category $domain): CategoryModel
    {
        $model = new CategoryModel();
        $model->id = $domain->id();
        $model->name = $domain->name();

        return $model;
    }
}
