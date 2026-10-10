<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\Loans\GetOverdueLoansService;
use Illuminate\Http\JsonResponse;

final class OverdueController
{
    public function __construct(
        private readonly GetOverdueLoansService $getOverdueLoansService
    ) {
    }

    public function index(): JsonResponse
    {
        $items = $this->getOverdueLoansService->execute();

        return response()->json([
            'items' => $items,
        ], 200);
    }
}
