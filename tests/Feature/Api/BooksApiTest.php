<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Eloquent\Models\CategoryModel;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class BooksApiTest extends TestCase
{
    private string $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();

        $category = CategoryModel::first();
        $this->categoryId = (string) $category->id;
    }

    public function test_librarian_cannot_create_book(): void
    {
        $librarianToken = $this->getLibrarianToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$librarianToken}",
        ])->postJson('/api/v1/books', [
            'title' => 'Rayuela',
            'author' => 'Julio Cortázar',
            'isbn' => '9789870402121',
            'category_id' => $this->categoryId,
            'total_copies' => 3,
        ]);

        $response->assertStatus(403);
        $response->assertJson(['code' => 'FORBIDDEN_ROLE']);
    }

    public function test_admin_creates_book_with_available_copies_equal_to_total(): void
    {
        $adminToken = $this->getAdminToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->postJson('/api/v1/books', [
            'title' => 'Cien años de soledad',
            'author' => 'Gabriel García Márquez',
            'isbn' => '9780307474728',
            'category_id' => $this->categoryId,
            'total_copies' => 5,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'title' => 'Cien años de soledad',
            'author' => 'Gabriel García Márquez',
            'isbn' => '9780307474728',
            'total_copies' => 5,
            'available_copies' => 5,
            'cover_key' => null,
        ]);
        $this->assertNotEmpty($response->json('id'));
        $this->assertSame($this->categoryId, $response->json('category.id'));
    }

    public function test_create_book_with_duplicate_isbn_returns_409(): void
    {
        $adminToken = $this->getAdminToken();

        $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->postJson('/api/v1/books', [
            'title' => 'Ficciones',
            'author' => 'Jorge Luis Borges',
            'isbn' => '9788420633114',
            'category_id' => $this->categoryId,
            'total_copies' => 2,
        ])->assertStatus(201);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->postJson('/api/v1/books', [
            'title' => 'Ficciones Edición Anotada',
            'author' => 'Jorge Luis Borges',
            'isbn' => '9788420633114',
            'category_id' => $this->categoryId,
            'total_copies' => 1,
        ]);

        $response->assertStatus(409);
        $response->assertJson([
            'code' => 'ISBN_TAKEN',
            'message' => 'El ISBN «9788420633114» ya está registrado en otro libro activo.',
        ]);
    }

    public function test_search_and_get_book(): void
    {
        $adminToken = $this->getAdminToken();

        $created = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->postJson('/api/v1/books', [
            'title' => 'Don Quijote de la Mancha',
            'author' => 'Miguel de Cervantes',
            'isbn' => '9788424116286',
            'category_id' => $this->categoryId,
            'total_copies' => 4,
        ])->json();

        $bookId = $created['id'];

        // Get single
        $showResponse = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->getJson("/api/v1/books/{$bookId}");

        $showResponse->assertStatus(200);
        $showResponse->assertJson([
            'id' => $bookId,
            'title' => 'Don Quijote de la Mancha',
        ]);

        // Search
        $searchResponse = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->getJson('/api/v1/books?q=Quijote');

        $searchResponse->assertStatus(200);
        $searchResponse->assertJsonStructure([
            'items',
            'page',
            'size',
            'total',
        ]);
        $this->assertSame(1, $searchResponse->json('total'));
    }

    public function test_update_book_adjusts_available_copies(): void
    {
        $adminToken = $this->getAdminToken();

        $created = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->postJson('/api/v1/books', [
            'title' => 'El Aleph',
            'author' => 'Jorge Luis Borges',
            'isbn' => '9788420633121',
            'category_id' => $this->categoryId,
            'total_copies' => 3,
        ])->json();

        $bookId = $created['id'];

        $updateResponse = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->putJson("/api/v1/books/{$bookId}", [
            'title' => 'El Aleph (Edición Definitiva)',
            'author' => 'Jorge Luis Borges',
            'category_id' => $this->categoryId,
            'total_copies' => 5,
        ]);

        $updateResponse->assertStatus(200);
        $updateResponse->assertJson([
            'title' => 'El Aleph (Edición Definitiva)',
            'total_copies' => 5,
            'available_copies' => 5,
        ]);
    }

    public function test_delete_book_soft_deletes_and_subsequent_get_returns_404(): void
    {
        $adminToken = $this->getAdminToken();

        $created = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->postJson('/api/v1/books', [
            'title' => 'Libro a Eliminar',
            'author' => 'Autor Temporal',
            'isbn' => '9780000000001',
            'category_id' => $this->categoryId,
            'total_copies' => 1,
        ])->json();

        $bookId = $created['id'];

        // Delete
        $deleteResponse = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->deleteJson("/api/v1/books/{$bookId}");

        $deleteResponse->assertStatus(204);

        // Subsequent show returns 404
        $showResponse = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->getJson("/api/v1/books/{$bookId}");

        $showResponse->assertStatus(404);
        $showResponse->assertJson(['code' => 'NOT_FOUND']);
    }

    public function test_upload_cover_success_and_retrieval_and_deletion(): void
    {
        $adminToken = $this->getAdminToken();

        $created = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->postJson('/api/v1/books', [
            'title' => 'Libro con Portada',
            'author' => 'Autor Ilustrado',
            'isbn' => '9780000000002',
            'category_id' => $this->categoryId,
            'total_copies' => 2,
        ])->json();

        $bookId = $created['id'];

        // 1. Upload valid PNG (100 KB)
        $fakePng = UploadedFile::fake()->image('cover.png', 200, 200);
        $uploadResponse = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->put("/api/v1/books/{$bookId}/cover", [
            'file' => $fakePng,
        ]);

        $uploadResponse->assertStatus(200);
        $coverKey = $uploadResponse->json('cover_key');
        $this->assertNotEmpty($coverKey);

        // 2. Retrieve cover
        $getResponse = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->get("/api/v1/books/{$bookId}/cover");

        $getResponse->assertStatus(200);
        $this->assertSame('image/png', $getResponse->headers->get('Content-Type'));

        // 3. Delete cover
        $delCover = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->deleteJson("/api/v1/books/{$bookId}/cover");

        $delCover->assertStatus(204);

        // 4. Subsequent cover retrieval returns 404
        $getAfterDel = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->getJson("/api/v1/books/{$bookId}/cover");

        $getAfterDel->assertStatus(404);
        $getAfterDel->assertJson(['code' => 'NOT_FOUND']);
    }

    public function test_upload_cover_exceeding_2mb_returns_422(): void
    {
        $adminToken = $this->getAdminToken();

        $created = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->postJson('/api/v1/books', [
            'title' => 'Libro Portada Pesada',
            'author' => 'Autor Pesado',
            'isbn' => '9780000000003',
            'category_id' => $this->categoryId,
            'total_copies' => 2,
        ])->json();

        $bookId = $created['id'];

        // Fake image larger than 2 MB (3000 KB)
        $oversizedFile = UploadedFile::fake()->create('huge.png', 3000, 'image/png');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->put("/api/v1/books/{$bookId}/cover", [
            'file' => $oversizedFile,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'code' => 'VALIDATION_ERROR',
        ]);
    }
}
