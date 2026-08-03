<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok_structure(): void
    {
        $response = $this->getJson('/api/v1/diagnostics/health');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'checks' => [
                        'database' => ['status', 'message'],
                        'redis' => ['status', 'message'],
                        'queue' => ['status', 'message'],
                    ],
                    'timestamp',
                ],
            ]);
    }
}
