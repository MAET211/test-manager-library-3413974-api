<?php

declare(strict_types=1);

namespace App\Application\Ports\Outbound;

use App\Domain\Entities\Category;

interface CategoryRepositoryInterface
{
    public function findById(string $id): ?Category;

    /**
     * @return array<int, Category>
     */
    public function findAll(): array;
}
