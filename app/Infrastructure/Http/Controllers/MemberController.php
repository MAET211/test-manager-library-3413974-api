<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\Members\CreateMemberService;
use App\Application\Services\Members\GetMemberService;
use App\Application\Services\Members\SearchMembersService;
use App\Domain\Entities\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class MemberController
{
    public function __construct(
        private readonly SearchMembersService $searchMembersService,
        private readonly GetMemberService $getMemberService,
        private readonly CreateMemberService $createMemberService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = $request->query('q');
        $page = (int) $request->query('page', 1);
        $size = (int) $request->query('size', 20);

        $result = $this->searchMembersService->execute(
            query: $query !== null ? (string) $query : null,
            page: $page,
            size: $size
        );

        $items = array_map(static fn (Member $member) => [
            'id' => $member->id(),
            'full_name' => $member->fullName(),
            'document_number' => $member->documentNumber(),
        ], $result['items']);

        return response()->json([
            'items' => $items,
            'page' => $result['page'],
            'size' => $result['size'],
            'total' => $result['total'],
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $result = $this->getMemberService->execute($id);

        return response()->json([
            'id' => $result['member']->id(),
            'full_name' => $result['member']->fullName(),
            'document_number' => $result['member']->documentNumber(),
            'active_loans' => $result['active_loans'],
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string'],
            'document_number' => ['required', 'string'],
        ]);

        $id = (string) Str::uuid();

        $member = $this->createMemberService->execute(
            id: $id,
            fullName: (string) $validated['full_name'],
            documentNumber: (string) $validated['document_number']
        );

        return response()->json([
            'id' => $member->id(),
            'full_name' => $member->fullName(),
            'document_number' => $member->documentNumber(),
        ], 201);
    }
}
