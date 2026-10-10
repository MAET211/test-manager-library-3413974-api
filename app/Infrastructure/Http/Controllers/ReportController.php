<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Services\Reports\GetMostBorrowedReportService;
use App\Domain\Exceptions\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReportController
{
    public function __construct(
        private readonly GetMostBorrowedReportService $getMostBorrowedReportService
    ) {
    }

    public function mostBorrowed(Request $request): JsonResponse
    {
        $from = $request->query('from');
        $to = $request->query('to');

        if ($from === null || trim((string) $from) === '') {
            throw new ValidationException(
                message: 'El parámetro de consulta «from» es obligatorio.',
                details: [['field' => 'from', 'issue' => 'parámetro requerido (formato YYYY-MM-DD)']]
            );
        }

        if ($to === null || trim((string) $to) === '') {
            throw new ValidationException(
                message: 'El parámetro de consulta «to» es obligatorio.',
                details: [['field' => 'to', 'issue' => 'parámetro requerido (formato YYYY-MM-DD)']]
            );
        }

        $report = $this->getMostBorrowedReportService->execute(
            fromDate: trim((string) $from),
            toDate: trim((string) $to)
        );

        return response()->json($report, 200);
    }
}
