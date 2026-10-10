<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

final class AuthApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase();
    }

    public function test_login_success_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'admin',
            'password' => 'admin123456',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'role',
        ]);
        $this->assertSame('Bearer', $response->json('token_type'));
        $this->assertSame(28800, $response->json('expires_in'));
        $this->assertSame('admin', $response->json('role'));
        $this->assertNotEmpty($response->json('access_token'));
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'admin',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'code' => 'INVALID_CREDENTIALS',
            'message' => 'Usuario o contraseña incorrectos.',
            'details' => [],
        ]);
    }

    public function test_login_fails_with_unknown_username(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'non_existent_user',
            'password' => 'some_password',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'code' => 'INVALID_CREDENTIALS',
            'message' => 'Usuario o contraseña incorrectos.',
            'details' => [],
        ]);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(401);
        $response->assertJson([
            'code' => 'INVALID_CREDENTIALS',
            'message' => 'Token de autenticación inválido o ausente.',
            'details' => [],
        ]);
    }

    public function test_invalid_bearer_token_returns_401(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid.token.payload',
        ])->getJson('/api/v1/categories');

        $response->assertStatus(401);
        $response->assertJson([
            'code' => 'INVALID_CREDENTIALS',
            'message' => 'Token de autenticación inválido o ausente.',
            'details' => [],
        ]);
    }
}
