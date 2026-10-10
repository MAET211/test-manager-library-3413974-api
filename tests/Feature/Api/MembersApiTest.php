<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

final class MembersApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    public function test_create_member_as_librarian_succeeds(): void
    {
        $token = $this->getLibrarianToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/members', [
            'full_name' => 'María Rodríguez',
            'document_number' => '1020304050',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'full_name' => 'María Rodríguez',
            'document_number' => '1020304050',
        ]);
        $this->assertNotEmpty($response->json('id'));
    }

    public function test_create_member_duplicate_document_returns_409(): void
    {
        $token = $this->getLibrarianToken();

        $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/members', [
            'full_name' => 'Socio Uno',
            'document_number' => '99887766',
        ])->assertStatus(201);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/members', [
            'full_name' => 'Socio Dos',
            'document_number' => '99887766',
        ]);

        $response->assertStatus(409);
        $response->assertJson([
            'code' => 'DOCUMENT_TAKEN',
            'message' => 'El documento de identidad «99887766» ya se encuentra registrado.',
        ]);
    }

    public function test_get_member_detail_with_active_loans_count(): void
    {
        $token = $this->getLibrarianToken();

        $created = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/members', [
            'full_name' => 'Pedro Paramo',
            'document_number' => '1122334455',
        ])->json();

        $memberId = $created['id'];

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson("/api/v1/members/{$memberId}");

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $memberId,
            'full_name' => 'Pedro Paramo',
            'document_number' => '1122334455',
            'active_loans' => 0,
        ]);
    }

    public function test_get_non_existent_member_returns_404(): void
    {
        $token = $this->getLibrarianToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/members/00000000-0000-0000-0000-000000000999');

        $response->assertStatus(404);
        $response->assertJson(['code' => 'NOT_FOUND']);
    }
}
