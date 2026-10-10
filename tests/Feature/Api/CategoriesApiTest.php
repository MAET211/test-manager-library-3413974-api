<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

final class CategoriesApiTest extends TestCase
{
    public function test_categories_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(401);
        $response->assertJson(['code' => 'INVALID_CREDENTIALS']);
    }

    public function test_categories_returns_five_seeded_categories_sorted_by_name(): void
    {
        $token = $this->getAdminToken();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/categories');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertIsArray($data);
        $this->assertCount(5, $data);

        $names = array_column($data, 'name');
        $sortedNames = $names;
        sort($sortedNames, SORT_STRING);

        $this->assertSame($sortedNames, $names);

        foreach ($data as $cat) {
            $this->assertArrayHasKey('id', $cat);
            $this->assertArrayHasKey('name', $cat);
        }
    }
}
