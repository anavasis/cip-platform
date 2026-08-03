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
            'source_id' => 'source-1',
            'body' => 'secret body',
            'nested' => ['raw_body' => 'secret raw body', 'safe' => true],
        ]);
        $response = $this->withHeader('Authorization', 'Bearer '.$this->authToken($owner))
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.evidence_store', 'in_memory')
            ->assertJsonPath('data.last_ingestion.nested.safe', true);

        $this->assertNoBodyKeys($response->json('data'));
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
