<?php

declare(strict_types=1);

namespace App\Application\Services\Categories;

use App\Application\Ports\Outbound\CategoryRepositoryInterface;
use App\Domain\Entities\Category;

final readonly class ListCategoriesService
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository
    ) {
    }

    /**
     * @return array<int, Category>
     */
    public function execute(): array
    {
        $categories = $this->categoryRepository->findAll();

        usort($categories, static fn (Category $a, Category $b) => strcmp($a->name(), $b->name()));

        return $categories;
    }
}
