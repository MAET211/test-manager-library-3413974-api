<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class HealthTest extends TestCase
{
    public function test_api_v1_health_returns_status_ok(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertExactJson([
                'status' => 'ok',
            ]);
    }

    public function test_health_alias_returns_status_ok(): void
    {
        $response = $this->getJson('/health');

        $response->assertStatus(200)
            ->assertExactJson([
                'status' => 'ok',
            ]);
    }
}
