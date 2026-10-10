<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Ports\Outbound\ClockInterface;
use App\Application\Services\Loans\GetLoanService;
use App\Application\Services\Loans\LendBooksService;
use App\Application\Services\Loans\ListLoansService;
use App\Application\Services\Loans\ReturnItemService;
use App\Domain\Entities\Loan;
use App\Domain\Entities\LoanItem;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class LoanController
{
    public function __construct(
        private readonly LendBooksService $lendBooksService,
        private readonly ListLoansService $listLoansService,
        private readonly GetLoanService $getLoanService,
        private readonly ReturnItemService $returnItemService,
        private readonly ClockInterface $clock
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'member_id' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1', 'max:3'],
            'items.*.book_id' => ['required', 'string'],
        ]);

        $userId = (string) $request->attributes->get('auth_user_id');
        $loanId = (string) Str::uuid();

        $loan = $this->lendBooksService->execute(
            loanId: $loanId,
            memberId: (string) $validated['member_id'],
            loanedByUserId: $userId,
            itemRequests: $validated['items']
        );

        return response()->json($this->formatLoan($loan, $this->clock->now()), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $fromStr = $request->query('from');
        $toStr = $request->query('to');
        $status = $request->query('status');
        $memberId = $request->query('member_id');
        $page = (int) $request->query('page', 1);
        $size = (int) $request->query('size', 20);

        $utc = new DateTimeZone('UTC');
        $from = $fromStr !== null ? DateTimeImmutable::createFromFormat('Y-m-d', (string) $fromStr, $utc)?->setTime(0, 0, 0) : null;
        $to = $toStr !== null ? DateTimeImmutable::createFromFormat('Y-m-d', (string) $toStr, $utc)?->setTime(23, 59, 59) : null;

        $result = $this->listLoansService->execute(
            from: $from ?: null,
            to: $to ?: null,
            status: $status !== null ? (string) $status : null,
            memberId: $memberId !== null ? (string) $memberId : null,
            page: $page,
            size: $size
        );

        $now = $this->clock->now();
        $items = array_map(fn (Loan $loan) => $this->formatLoan($loan, $now), $result['items']);

        return response()->json([
            'items' => $items,
            'page' => $result['page'],
            'size' => $result['size'],
            'total' => $result['total'],
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $loan = $this->getLoanService->execute($id);

        return response()->json($this->formatLoan($loan, $this->clock->now()), 200);
    }

    public function returnItem(string $loanId, string $itemId): JsonResponse
    {
        $item = $this->returnItemService->execute($loanId, $itemId);

        return response()->json([
            'id' => $item->id(),
            'book_id' => $item->bookId(),
            'book_title' => $item->bookTitle(),
            'isbn' => $item->isbn(),
            'category_name' => $item->categoryName(),
            'due_at' => $item->dueAt()->format('Y-m-d\TH:i:s\Z'),
            'returned_at' => $item->returnedAt()?->format('Y-m-d\TH:i:s\Z'),
        ], 200);
    }

    private function formatLoan(Loan $loan, DateTimeImmutable $now): array
    {
        return [
            'id' => $loan->id(),
            'member_id' => $loan->memberId(),
            'loaned_at' => $loan->loanedAt()->format('Y-m-d\TH:i:s\Z'),
            'status' => $loan->status($now)->value,
            'items' => array_map(static fn (LoanItem $item) => [
                'id' => $item->id(),
                'book_id' => $item->bookId(),
                'book_title' => $item->bookTitle(),
                'isbn' => $item->isbn(),
                'category_name' => $item->categoryName(),
                'due_at' => $item->dueAt()->format('Y-m-d\TH:i:s\Z'),
                'returned_at' => $item->returnedAt()?->format('Y-m-d\TH:i:s\Z'),
            ], $loan->items()),
        ];
    }
}
