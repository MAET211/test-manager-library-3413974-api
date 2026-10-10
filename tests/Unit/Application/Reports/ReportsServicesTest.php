<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Reports;

use App\Application\Services\Loans\GetOverdueLoansService;
use App\Application\Services\Reports\GetMostBorrowedReportService;
use App\Domain\Exceptions\InvalidDateRangeException;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryLoanRepository;
use Tests\Fakes\InMemoryMostBorrowedReportReader;

final class ReportsServicesTest extends TestCase
{
    private InMemoryLoanRepository $loanRepository;
    private InMemoryMostBorrowedReportReader $reportReader;
    private GetOverdueLoansService $overdueService;
    private GetMostBorrowedReportService $reportService;
    private DateTimeZone $utc;

    protected function setUp(): void
    {
        $this->utc = new DateTimeZone('UTC');
        $this->loanRepository = new InMemoryLoanRepository();
        $this->reportReader = new InMemoryMostBorrowedReportReader();
        $fakeClock = new \Tests\Fakes\FakeClock(new DateTimeImmutable('2026-10-25 10:00:00', $this->utc));

        $this->overdueService = new GetOverdueLoansService($this->loanRepository, $fakeClock);
        $this->reportService = new GetMostBorrowedReportService($this->reportReader);
    }

    public function test_get_overdue_loans_returns_items_sorted_by_days_late_descending(): void
    {
        $now = new DateTimeImmutable('2026-10-25 10:00:00', $this->utc);

        // Simulamos préstamos y configuramos ítems vencidos en el fake
        $this->loanRepository->save(new \App\Domain\Entities\Loan(
            id: 'l-1',
            memberId: 'm-1',
            loanedByUserId: 'u-1',
            loanedAt: new DateTimeImmutable('2026-10-01 10:00:00', $this->utc),
            items: [
                new \App\Domain\Entities\LoanItem('it-1', 'l-1', 'b-1', 'Libro Muy Vencido', '9780000000001', 'Novela', new DateTimeImmutable('2026-10-10 10:00:00', $this->utc)),
            ]
        ));

        $this->loanRepository->save(new \App\Domain\Entities\Loan(
            id: 'l-2',
            memberId: 'm-2',
            loanedByUserId: 'u-1',
            loanedAt: new DateTimeImmutable('2026-10-05 10:00:00', $this->utc),
            items: [
                new \App\Domain\Entities\LoanItem('it-2', 'l-2', 'b-2', 'Libro Poco Vencido', '9780000000002', 'Ciencia', new DateTimeImmutable('2026-10-20 10:00:00', $this->utc)),
            ]
        ));

        $this->loanRepository->setMemberName('m-1', 'Juan Pérez');
        $this->loanRepository->setMemberName('m-2', 'María Gómez');

        $result = $this->overdueService->execute();

        $this->assertCount(2, $result);
        // CA-09.2: Mayor retraso primero
        $this->assertSame('it-1', $result[0]['item_id']);
        $this->assertSame('Juan Pérez', $result[0]['member_name']);
        $this->assertGreaterThan($result[1]['days_late'], $result[0]['days_late']);
    }

    public function test_get_most_borrowed_report_aggregates_and_sorts_correctly(): void
    {
        $d1 = new DateTimeImmutable('2026-10-02', $this->utc);
        $d2 = new DateTimeImmutable('2026-10-15', $this->utc);

        // Sembrar en el reader 3 préstamos de Libro A y 1 de Libro B
        $this->reportReader->addLoanEntry('b-1', 'Cien años de soledad', 'Novela', $d1);
        $this->reportReader->addLoanEntry('b-1', 'Cien años de soledad', 'Novela', $d2);
        $this->reportReader->addLoanEntry('b-1', 'Cien años de soledad', 'Novela', $d2);
        $this->reportReader->addLoanEntry('b-2', 'Breve historia del tiempo', 'Ciencia', $d1);

        $report = $this->reportService->execute('2026-10-01', '2026-10-31');

        $this->assertSame('2026-10-01', $report['from']);
        $this->assertSame('2026-10-31', $report['to']);
        $this->assertCount(2, $report['rows']);

        // CA-10.1: Ordenado por times_borrowed descendente
        $this->assertSame('Cien años de soledad', $report['rows'][0]['book_title']);
        $this->assertSame(3, $report['rows'][0]['times_borrowed']);
        $this->assertSame('Breve historia del tiempo', $report['rows'][1]['book_title']);
        $this->assertSame(1, $report['rows'][1]['times_borrowed']);
    }

    public function test_get_most_borrowed_report_preserves_frozen_snapshot_names(): void
    {
        // ADR-004: Dos préstamos con el título viejo y uno con el nuevo generan dos filas separadas
        $d = new DateTimeImmutable('2026-10-05', $this->utc);

        $this->reportReader->addLoanEntry('b-1', 'Título Antiguo', 'Novela', $d);
        $this->reportReader->addLoanEntry('b-1', 'Título Antiguo', 'Novela', $d);
        $this->reportReader->addLoanEntry('b-1', 'Título Nuevo', 'Novela', $d);

        $report = $this->reportService->execute('2026-10-01', '2026-10-31');

        $this->assertCount(2, $report['rows']);
        $this->assertSame('Título Antiguo', $report['rows'][0]['book_title']);
        $this->assertSame(2, $report['rows'][0]['times_borrowed']);
        $this->assertSame('Título Nuevo', $report['rows'][1]['book_title']);
        $this->assertSame(1, $report['rows'][1]['times_borrowed']);
    }

    public function test_get_most_borrowed_report_fails_with_invalid_date_range(): void
    {
        $this->expectException(InvalidDateRangeException::class);
        $this->expectExceptionMessage('La fecha final no puede ser anterior a la fecha inicial.');

        // to < from
        $this->reportService->execute('2026-10-31', '2026-10-01');
    }
}
