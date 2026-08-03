<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_record_and_list_metrics(): void
    {
        ['user' => $user] = $this->createUserWithOrg('owner');
        $token = $this->authToken($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/monitoring/metrics', [
                'name' => 'api.requests',
                'value' => 42,
                'tags' => ['endpoint' => 'health'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'api.requests');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/monitoring/metrics?name=api.requests')
            ->assertOk();
    }
}
