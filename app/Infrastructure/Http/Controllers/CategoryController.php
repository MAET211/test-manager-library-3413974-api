<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\Categories\ListCategoriesService;
use Illuminate\Http\JsonResponse;

final class CategoryController
{
    public function __construct(
        private readonly ListCategoriesService $listCategoriesService
    ) {
    }

    public function index(): JsonResponse
    {
        $categories = $this->listCategoriesService->execute();

        $response = array_map(static fn ($cat) => [
            'id' => $cat->id(),
            'name' => $cat->name(),
        ], $categories);

        return response()->json($response, 200);
    }
}
