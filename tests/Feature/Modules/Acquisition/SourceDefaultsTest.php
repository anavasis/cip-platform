<?php

namespace Tests\Feature\Modules\Acquisition;

use App\Infrastructure\Persistence\Models\Project;
use Tests\TestCase;

class SourceDefaultsTest extends TestCase
{
    public function test_new_source_is_disabled_and_manual_only_by_default(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->authToken($owner))
            ->postJson($this->sourcesUrl($organization->id, $project->id), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.manual_only', true)
            ->assertJsonPath('data.acquire_interval_seconds', 3600);

        $this->assertDatabaseHas('sources', [
            'id' => $response->json('data.id'),
            'enabled' => false,
            'manual_only' => true,
            'acquire_interval_seconds' => 3600,
        ]);
    }

    public function test_acquire_interval_is_accepted_on_create_and_update(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id);
        $payload = $this->payload();
        $payload['acquire_interval_seconds'] = 900;
        $client = $this->withHeader('Authorization', 'Bearer '.$this->authToken($owner));

        $sourceId = $client
            ->postJson($this->sourcesUrl($organization->id, $project->id), $payload)
            ->assertCreated()
            ->assertJsonPath('data.acquire_interval_seconds', 900)
            ->json('data.id');

        $client
            ->patchJson(
                $this->sourcesUrl($organization->id, $project->id).'/'.$sourceId,
                ['acquire_interval_seconds' => 1800],
            )
            ->assertOk()
            ->assertJsonPath('data.acquire_interval_seconds', 1800);
    }

    private function createProject(string $organizationId, string $userId): Project
    {
        return Project::create([
            'organization_id' => $organizationId,
            'name' => 'Source Defaults',
            'slug' => 'source-defaults-'.uniqid(),
            'created_by' => $userId,
        ]);
    }

    private function sourcesUrl(string $organizationId, string $projectId): string
    {
        return "/api/v1/organizations/{$organizationId}/projects/{$projectId}/sources";
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'slug' => 'default-feed',
            'name' => 'Default Feed',
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => 'https://example.com/default.xml',
            'allowed_domains' => ['example.com'],
        ];
    }
}
