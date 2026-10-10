<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Application\Ports\Outbound\CategoryRepositoryInterface;
use App\Domain\Entities\Category;

final class InMemoryCategoryRepository implements CategoryRepositoryInterface
{
    /**
     * @var array<string, Category>
     */
    private array $categories = [];

    public function __construct()
    {
        $this->add(new Category('11111111-1111-4111-8111-111111111111', 'Novela'));
        $this->add(new Category('22222222-2222-4222-8222-222222222222', 'Ciencia'));
        $this->add(new Category('33333333-3333-4333-8333-333333333333', 'Tecnología'));
        $this->add(new Category('44444444-4444-4444-8444-444444444444', 'Historia'));
        $this->add(new Category('55555555-5555-4555-8555-555555555555', 'Infantil'));
    }

    public function add(Category $category): void
    {
        $this->categories[$category->id()] = $category;
    }

    public function findById(string $id): ?Category
    {
        return $this->categories[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->categories);
    }
}
