<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use Tests\TestCase;

final class OverdueAndReportsApiTest extends TestCase
{
    private string $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();

        $category = CategoryModel::first();
        $this->categoryId = (string) $category->id;
    }

    public function test_get_overdue_loans_returns_empty_when_no_overdue(): void
    {
        $token = $this->getLibrarianToken();

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/loans/overdue');

        $response->assertStatus(200);
        $response->assertJson(['items' => []]);
    }

    public function test_report_most_borrowed_as_librarian_returns_403(): void
    {
        $librarianToken = $this->getLibrarianToken();

        $response = $this->withHeaders(['Authorization' => "Bearer {$librarianToken}"])
            ->getJson('/api/v1/reports/most-borrowed?from=2026-01-01&to=2026-12-31');

        $response->assertStatus(403);
        $response->assertJson(['code' => 'FORBIDDEN_ROLE']);
    }

    public function test_report_most_borrowed_missing_date_params_returns_422(): void
    {
        $adminToken = $this->getAdminToken();

        $response = $this->withHeaders(['Authorization' => "Bearer {$adminToken}"])
            ->getJson('/api/v1/reports/most-borrowed');

        $response->assertStatus(422);
        $response->assertJson(['code' => 'VALIDATION_ERROR']);
    }

    public function test_report_most_borrowed_inverted_date_range_returns_422(): void
    {
        $adminToken = $this->getAdminToken();

        $response = $this->withHeaders(['Authorization' => "Bearer {$adminToken}"])
            ->getJson('/api/v1/reports/most-borrowed?from=2026-12-31&to=2026-01-01');

        $response->assertStatus(422);
        $response->assertJson(['code' => 'INVALID_DATE_RANGE']);
    }

    public function test_report_most_borrowed_as_admin_succeeds(): void
    {
        $adminToken = $this->getAdminToken();

        $response = $this->withHeaders(['Authorization' => "Bearer {$adminToken}"])
            ->getJson('/api/v1/reports/most-borrowed?from=2026-01-01&to=2026-12-31');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'from',
            'to',
            'rows',
        ]);
        $this->assertSame('2026-01-01', $response->json('from'));
        $this->assertSame('2026-12-31', $response->json('to'));
    }
}
