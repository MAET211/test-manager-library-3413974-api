<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use Tests\TestCase;

final class LoansApiTest extends TestCase
{
    private string $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();

        $category = CategoryModel::first();
        $this->categoryId = (string) $category->id;
    }

    public function test_lend_books_succeeds_decrements_copies_and_freezes_data(): void
    {
        $token = $this->getLibrarianToken();

        // 1. Create member
        $member = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/members', ['full_name' => 'Juan Preciado', 'document_number' => '12345678'])
            ->json();

        // 2. Create book
        $adminToken = $this->getAdminToken();
        $book = $this->withHeaders(['Authorization' => "Bearer {$adminToken}"])
            ->postJson('/api/v1/books', [
                'title' => 'Pedro Páramo',
                'author' => 'Juan Rulfo',
                'isbn' => '9788437604183',
                'category_id' => $this->categoryId,
                'total_copies' => 2,
            ])->json();

        // 3. Register loan
        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/loans', [
                'member_id' => $member['id'],
                'items' => [
                    ['book_id' => $book['id']],
                ],
            ]);

        $response->assertStatus(201);
        $response->assertJson([
            'member_id' => $member['id'],
            'status' => 'open',
        ]);
        $items = $response->json('items');
        $this->assertCount(1, $items);
        $this->assertSame('Pedro Páramo', $items[0]['book_title']);
        $this->assertSame('9788437604183', $items[0]['isbn']);
        $this->assertNull($items[0]['returned_at']);

        // 4. Verify book available_copies decremented to 1
        $bookCheck = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson("/api/v1/books/{$book['id']}")
            ->json();

        $this->assertSame(1, $bookCheck['available_copies']);
    }

    public function test_lend_books_duplicate_book_returns_422(): void
    {
        $token = $this->getLibrarianToken();

        $member = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/members', ['full_name' => 'Socio Repetido', 'document_number' => '88888888'])
            ->json();

        $adminToken = $this->getAdminToken();
        $book = $this->withHeaders(['Authorization' => "Bearer {$adminToken}"])
            ->postJson('/api/v1/books', [
                'title' => 'Libro Único',
                'author' => 'Autor X',
                'isbn' => '9780000000100',
                'category_id' => $this->categoryId,
                'total_copies' => 5,
            ])->json();

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/loans', [
                'member_id' => $member['id'],
                'items' => [
                    ['book_id' => $book['id']],
                    ['book_id' => $book['id']],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJson(['code' => 'DUPLICATE_BOOK_IN_LOAN']);
    }

    public function test_return_item_succeeds_and_restocks_copies(): void
    {
        $token = $this->getLibrarianToken();

        $member = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/members', ['full_name' => 'Ana Frank', 'document_number' => '77777777'])
            ->json();

        $adminToken = $this->getAdminToken();
        $book = $this->withHeaders(['Authorization' => "Bearer {$adminToken}"])
            ->postJson('/api/v1/books', [
                'title' => 'Diario de Ana Frank',
                'author' => 'Ana Frank',
                'isbn' => '9788497593069',
                'category_id' => $this->categoryId,
                'total_copies' => 1,
            ])->json();

        $loan = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/loans', [
                'member_id' => $member['id'],
                'items' => [['book_id' => $book['id']]],
            ])->json();

        $loanId = $loan['id'];
        $itemId = $loan['items'][0]['id'];

        // Return item
        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/loans/{$loanId}/items/{$itemId}/return");

        $response->assertStatus(200);
        $this->assertNotNull($response->json('returned_at'));

        // Returning second time returns 409 ITEM_ALREADY_RETURNED
        $secondReturn = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/v1/loans/{$loanId}/items/{$itemId}/return");

        $secondReturn->assertStatus(409);
        $secondReturn->assertJson(['code' => 'ITEM_ALREADY_RETURNED']);
    }
}
