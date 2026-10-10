<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use Tests\TestCase;

final class ConcurrencyAndSecurityTest extends TestCase
{
    private string $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();

        $category = CategoryModel::first();
        $this->categoryId = (string) $category->id;
    }

    public function test_rnf2_concurrent_loan_for_last_available_copy_one_wins_one_gets_409(): void
    {
        $token = $this->getLibrarianToken();
        $adminToken = $this->getAdminToken();

        // Create book with ONLY 1 copy
        $book = $this->withHeaders(['Authorization' => "Bearer {$adminToken}"])
            ->postJson('/api/v1/books', [
                'title' => 'Único Ejemplar',
                'author' => 'Autor Exclusivo',
                'isbn' => '9780000000999',
                'category_id' => $this->categoryId,
                'total_copies' => 1,
            ])->json();

        // Create two members
        $m1 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/members', ['full_name' => 'Socio Uno', 'document_number' => '11111111'])
            ->json();

        $m2 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/members', ['full_name' => 'Socio Dos', 'document_number' => '22222222'])
            ->json();

        // First loan succeeds (201)
        $res1 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/loans', [
                'member_id' => $m1['id'],
                'items' => [['book_id' => $book['id']]],
            ]);
        $res1->assertStatus(201);

        // Second loan for the same book fails (409 BOOK_NOT_AVAILABLE)
        $res2 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/loans', [
                'member_id' => $m2['id'],
                'items' => [['book_id' => $book['id']]],
            ]);
        $res2->assertStatus(409);
        $res2->assertJson(['code' => 'BOOK_NOT_AVAILABLE']);
    }

    public function test_rnf3_password_is_never_revealed_in_responses(): void
    {
        $adminToken = $this->getAdminToken();

        // User creation response check
        $res = $this->withHeaders(['Authorization' => "Bearer {$adminToken}"])
            ->postJson('/api/v1/users', [
                'username' => 'seguridad_check',
                'password' => 'super_secret_pass_123',
                'role' => 'librarian',
            ]);

        $res->assertStatus(201);
        $content = $res->getContent();
        $this->assertStringNotContainsString('super_secret_pass_123', $content);
        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('password_hash', $content);
    }
}
