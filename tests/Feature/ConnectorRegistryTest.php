<?php

namespace Tests\Feature;

use App\Application\Services\ConnectorRegistryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectorRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_register_and_list_connector_types(): void
    {
        ['user' => $user] = $this->createUserWithOrg('owner');
        $token = $this->authToken($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/connectors/types', [
                'type' => 'custom_cms',
                'name' => 'Custom CMS',
                'description' => 'A custom connector',
            ])
            ->assertCreated();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/connectors/types');

        $response->assertOk();
        $types = collect($response->json('data'))->pluck('type');
        $this->assertTrue($types->contains('custom_cms'));
        $this->assertTrue($types->contains('http_rest'));
        $this->assertTrue($types->contains('webhook'));
        $this->assertTrue($types->contains('custom'));
    }

    public function test_can_attach_connector_to_project(): void
    {
        ['user' => $user, 'organization' => $org] = $this->createUserWithOrg('owner');
        $token = $this->authToken($user);

        $project = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/organizations/{$org->id}/projects", ['name' => 'Connector Project'])
            ->json('data');

        $connectorType = app(ConnectorRegistryService::class)->findTypeByType('http_rest');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/organizations/{$org->id}/projects/{$project['id']}/connectors", [
                'connector_type_id' => $connectorType->id,
                'name' => 'Primary Target',
                'config' => ['base_url' => 'https://example.com'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Primary Target');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/organizations/{$org->id}/projects/{$project['id']}/connectors")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
