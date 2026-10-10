<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

final class UsersApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    public function test_create_user_unauthenticated_returns_401(): void
    {
        $response = $this->postJson('/api/v1/users', [
            'username' => 'carlos',
            'password' => 'secret123',
            'role' => 'librarian',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['code' => 'INVALID_CREDENTIALS']);
    }

    public function test_create_user_as_librarian_returns_403_forbidden_role(): void
    {
        $token = $this->getLibrarianToken('librarian_user');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/users', [
            'username' => 'carlos',
            'password' => 'secret123',
            'role' => 'librarian',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'code' => 'FORBIDDEN_ROLE',
            'message' => 'No tiene permisos para realizar esta acción.',
            'details' => [],
        ]);
    }

    public function test_create_librarian_as_admin_succeeds_and_does_not_reveal_password(): void
    {
        $adminToken = $this->getAdminToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->postJson('/api/v1/users', [
            'username' => '  Carlos_Librarian  ',
            'password' => 'strong_password_123',
            'role' => 'librarian',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'username' => 'carlos_librarian',
            'role' => 'librarian',
        ]);
        $response->assertJsonMissing(['password', 'password_hash']);
        $this->assertNotEmpty($response->json('id'));
    }

    public function test_create_user_requesting_admin_role_returns_422(): void
    {
        $adminToken = $this->getAdminToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->postJson('/api/v1/users', [
            'username' => 'another_admin',
            'password' => 'secret123',
            'role' => 'admin',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'code' => 'VALIDATION_ERROR',
        ]);
    }

    public function test_create_user_with_duplicate_username_returns_409(): void
    {
        $adminToken = $this->getAdminToken();

        // First creation
        $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->postJson('/api/v1/users', [
            'username' => 'mario',
            'password' => 'secret123',
            'role' => 'librarian',
        ])->assertStatus(201);

        // Duplicate creation
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$adminToken}",
        ])->postJson('/api/v1/users', [
            'username' => 'Mario',
            'password' => 'another_secret',
            'role' => 'librarian',
        ]);

        $response->assertStatus(409);
        $response->assertJson([
            'code' => 'USERNAME_TAKEN',
            'message' => 'El nombre de usuario «mario» ya está en uso.',
        ]);
    }
}
