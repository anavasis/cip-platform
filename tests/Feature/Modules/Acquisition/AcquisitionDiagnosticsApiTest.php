<?php

namespace Tests\Feature\Modules\Acquisition;

use App\Domain\Shared\Enums\RoleScope;
use App\Infrastructure\Persistence\Models\OrganizationMembership;
use App\Infrastructure\Persistence\Models\Project;
use App\Infrastructure\Persistence\Models\Role;
use App\Infrastructure\Persistence\Models\User;
use App\Modules\Acquisition\Application\AcquisitionDiagnostics;
use Tests\TestCase;

class AcquisitionDiagnosticsApiTest extends TestCase
{
    public function test_diagnostics_response_never_returns_body_fields(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg('owner');
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Diagnostics Project',
            'slug' => 'diagnostics-project',
            'created_by' => $owner->id,
        ]);
        $url = "/api/v1/organizations/{$organization->id}/projects/{$project->id}/acquisition/diagnostics";

        app(AcquisitionDiagnostics::class)->record([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'source_id' => 'source-1',
            'body' => 'secret body',
            'nested' => ['raw_body' => 'secret raw body', 'safe' => true],
        ]);
        $response = $this->withHeader('Authorization', 'Bearer '.$this->authToken($owner))
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.evidence_store', 'in_memory')
            ->assertJsonPath('data.last_ingestion.nested.safe', true);

        $data = $response->json('data');
        $this->assertNoBodyKeys($data);
        $this->assertArrayNotHasKey('plugin_version', $data);
        $this->assertArrayNotHasKey('publishing', $data);
        $this->assertArrayNotHasKey('scheduler', $data);
        $this->assertArrayNotHasKey('ai', $data);
    }

    public function test_diagnostics_state_is_partitioned_by_project(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg('owner');
        $projectA = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Diagnostics A',
            'slug' => 'diagnostics-a',
            'created_by' => $owner->id,
        ]);
        $projectB = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Diagnostics B',
            'slug' => 'diagnostics-b',
            'created_by' => $owner->id,
        ]);
        $diagnostics = app(AcquisitionDiagnostics::class);
        $diagnostics->record([
            'organization_id' => $organization->id,
            'project_id' => $projectA->id,
            'source_id' => 'source-a',
        ]);
        $diagnostics->record([
            'organization_id' => $organization->id,
            'project_id' => $projectB->id,
            'source_id' => 'source-b',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->authToken($owner))
            ->getJson(
                "/api/v1/organizations/{$organization->id}/projects/{$projectA->id}/acquisition/diagnostics",
            )
            ->assertOk()
            ->assertJsonPath('data.last_ingestion.source_id', 'source-a')
            ->assertJsonMissing(['source_id' => 'source-b']);
    }

    public function test_diagnostics_requires_acquisition_diagnostics_permission(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg('owner');
        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => 'Diagnostics Project',
            'slug' => 'diagnostics-project',
            'created_by' => $owner->id,
        ]);
        $member = User::factory()->create();
        $memberRole = Role::query()
            ->where('name', 'member')
            ->where('scope', RoleScope::Organization)
            ->firstOrFail();
        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'role_id' => $memberRole->id,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->authToken($member))
            ->getJson(
                "/api/v1/organizations/{$organization->id}/projects/{$project->id}/acquisition/diagnostics",
            )
            ->assertForbidden();
    }

    /** @param array<int|string, mixed> $value */
    private function assertNoBodyKeys(array $value): void
    {
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $this->assertNotContains($key, ['body', 'raw_body', 'evidence_body']);
            }

            if (is_array($item)) {
                $this->assertNoBodyKeys($item);
            }
        }
    }
}
