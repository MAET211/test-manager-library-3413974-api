<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\Auth\CreateLibrarianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class UserController
{
    public function __construct(
        private readonly CreateLibrarianService $createLibrarianService
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'role' => ['required', 'string'],
        ]);

        $id = (string) Str::uuid();

        $result = $this->createLibrarianService->execute(
            id: $id,
            username: (string) $validated['username'],
            password: (string) $validated['password'],
            role: (string) $validated['role']
        );

        return response()->json($result, 201);
    }
}
