<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Ports\Outbound\CategoryRepositoryInterface;
use App\Application\Services\Books\CreateBookService;
use App\Application\Services\Books\DeleteBookService;
use App\Application\Services\Books\GetBookService;
use App\Application\Services\Books\SearchBooksService;
use App\Application\Services\Books\UpdateBookService;
use App\Domain\Entities\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

final class BookController
{
    public function __construct(
        private readonly SearchBooksService $searchBooksService,
        private readonly GetBookService $getBookService,
        private readonly CreateBookService $createBookService,
        private readonly UpdateBookService $updateBookService,
        private readonly DeleteBookService $deleteBookService,
        private readonly CategoryRepositoryInterface $categoryRepository
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = $request->query('q');
        $categoryId = $request->query('category_id');
        $page = (int) $request->query('page', 1);
        $size = (int) $request->query('size', 20);

        $result = $this->searchBooksService->execute(
            query: $query !== null ? (string) $query : null,
            categoryId: $categoryId !== null ? (string) $categoryId : null,
            page: $page,
            size: $size
        );

        $items = array_map(fn (Book $book) => $this->formatBook($book), $result['items']);

        return response()->json([
            'items' => $items,
            'page' => $result['page'],
            'size' => $result['size'],
            'total' => $result['total'],
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $book = $this->getBookService->execute($id);

        return response()->json($this->formatBook($book), 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string'],
            'author' => ['required', 'string'],
            'isbn' => ['required', 'string'],
            'category_id' => ['required', 'string'],
            'total_copies' => ['required', 'integer'],
        ]);

        $id = (string) Str::uuid();

        $book = $this->createBookService->execute(
            id: $id,
            title: (string) $validated['title'],
            author: (string) $validated['author'],
            isbn: (string) $validated['isbn'],
            categoryId: (string) $validated['category_id'],
            totalCopies: (int) $validated['total_copies']
        );

        return response()->json($this->formatBook($book), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string'],
            'author' => ['required', 'string'],
            'category_id' => ['required', 'string'],
            'total_copies' => ['required', 'integer'],
        ]);

        $book = $this->updateBookService->execute(
            id: $id,
            title: (string) $validated['title'],
            author: (string) $validated['author'],
            categoryId: (string) $validated['category_id'],
            totalCopies: (int) $validated['total_copies']
        );

        return response()->json($this->formatBook($book), 200);
    }

    public function destroy(string $id): Response
    {
        $this->deleteBookService->execute($id);

        return response()->noContent(204);
    }

    private function formatBook(Book $book): array
    {
        $category = $this->categoryRepository->findById($book->categoryId());

        return [
            'id' => $book->id(),
            'title' => $book->title(),
            'author' => $book->author(),
            'isbn' => $book->isbn()->value(),
            'category' => [
                'id' => $book->categoryId(),
                'name' => $category?->name() ?? '',
            ],
            'total_copies' => $book->totalCopies(),
            'available_copies' => $book->availableCopies(),
            'cover_key' => $book->coverKey(),
        ];
    }
}
