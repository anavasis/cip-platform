<?php

namespace Tests\Feature\Modules\Intelligence;

use App\Application\Services\ConfigurationService;
use App\Application\Services\OrganizationService;
use App\Application\Services\ProjectService;
use App\Domain\Shared\Enums\RoleScope;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\OrganizationMembership;
use App\Infrastructure\Persistence\Models\Project;
use App\Infrastructure\Persistence\Models\ProjectMembership;
use App\Infrastructure\Persistence\Models\Role;
use App\Infrastructure\Persistence\Models\User;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\ContentEntityModel;
use App\Modules\Intelligence\Infrastructure\Persistence\Models\RemotePostBindingModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class HubApiTest extends TestCase
{
    public function test_unauthenticated_request_rejected(): void
    {
        $this->getJson('/api/v1/organizations/'.Str::uuid().'/projects/'.Str::uuid().'/hub')
            ->assertUnauthorized();
    }

    public function test_wrong_organization_rejected(): void
    {
        ['user' => $ownerA, 'organization' => $orgA] = $this->createUserWithOrg();
        $projectA = $this->createProject($orgA->id, $ownerA->id, 'Project A');

        $userB = User::factory()->create();
        $orgB = Organization::create([
            'name' => 'Org B',
            'slug' => 'org-b-'.uniqid(),
            'created_by' => $userB->id,
        ]);
        $role = Role::query()->where('name', 'owner')->where('scope', RoleScope::Organization)->firstOrFail();
        OrganizationMembership::create([
            'organization_id' => $orgB->id,
            'user_id' => $userB->id,
            'role_id' => $role->id,
        ]);

        $this->actingAsUser($userB)
            ->getJson("/api/v1/organizations/{$orgA->id}/projects/{$projectA->id}/hub")
            ->assertForbidden();
    }

    public function test_wrong_project_rejected(): void
    {
        ['user' => $ownerA, 'organization' => $orgA] = $this->createUserWithOrg();
        $projectA = $this->createProject($orgA->id, $ownerA->id, 'Project A');

        $userB = User::factory()->create();
        $orgB = Organization::create([
            'name' => 'Org B Project',
            'slug' => 'org-b-project-'.uniqid(),
            'created_by' => $userB->id,
        ]);
        $role = Role::query()->where('name', 'owner')->where('scope', RoleScope::Organization)->firstOrFail();
        OrganizationMembership::create([
            'organization_id' => $orgB->id,
            'user_id' => $userB->id,
            'role_id' => $role->id,
        ]);
        $projectB = $this->createProject($orgB->id, $userB->id, 'Project B');

        $this->actingAsUser($ownerA)
            ->getJson("/api/v1/organizations/{$orgA->id}/projects/{$projectB->id}/hub")
            ->assertNotFound();
    }

    public function test_user_without_hub_view_rejected(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Perm Project');

        $viewer = User::factory()->create();
        $orgRole = Role::query()->where('name', 'member')->where('scope', RoleScope::Organization)->firstOrFail();
        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $viewer->id,
            'role_id' => $orgRole->id,
        ]);
        $projectRole = Role::query()->where('name', 'viewer')->where('scope', RoleScope::Project)->firstOrFail();
        ProjectMembership::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'user_id' => $viewer->id,
            'role_id' => $projectRole->id,
        ]);

        $this->actingAsUser($viewer)
            ->getJson("/api/v1/organizations/{$organization->id}/projects/{$project->id}/hub")
            ->assertForbidden();
    }

    public function test_authorized_hub_view_user_receives_schema_version_one(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Hub Project');

        $this->actingAsUser($owner)
            ->getJson("/api/v1/organizations/{$organization->id}/projects/{$project->id}/hub")
            ->assertOk()
            ->assertJsonPath('schema_version', 1);
    }

    public function test_endpoint_returns_public_safe_contract(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Contract Project');
        $this->seedEligibleRecord($organization->id, $project->id);

        $response = $this->actingAsUser($owner)
            ->getJson("/api/v1/organizations/{$organization->id}/projects/{$project->id}/hub");

        $response->assertOk()
            ->assertJsonStructure([
                'schema_version',
                'hub' => ['entity_id', 'url', 'title'],
                'generated_at',
                'freshness' => ['oldest_verified_at', 'stale_threshold_hours'],
                'records',
                'filters' => ['lifecycle', 'source_family', 'thematic'],
            ])
            ->assertJsonPath('records.0.entity_id', 'api-record')
            ->assertJsonPath('records.0.satellite_url', 'https://example.test/api-record');
    }

    public function test_endpoint_performs_no_mutation(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'No Mutation Project');
        $this->seedEligibleRecord($organization->id, $project->id);

        $entityCountBefore = ContentEntityModel::query()->count();
        $bindingCountBefore = RemotePostBindingModel::query()->count();

        $this->actingAsUser($owner)
            ->getJson("/api/v1/organizations/{$organization->id}/projects/{$project->id}/hub")
            ->assertOk();

        $this->assertSame($entityCountBefore, ContentEntityModel::query()->count());
        $this->assertSame($bindingCountBefore, RemotePostBindingModel::query()->count());
    }

    public function test_entity_from_another_project_never_leaks(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $projectA = $this->createProject($organization->id, $owner->id, 'Project A');
        $projectB = $this->createProject($organization->id, $owner->id, 'Project B');
        $this->seedEligibleRecord($organization->id, $projectA->id, 'project-a-record');

        $response = $this->actingAsUser($owner)
            ->getJson("/api/v1/organizations/{$organization->id}/projects/{$projectB->id}/hub");

        $response->assertOk();
        $this->assertSame([], $response->json('records'));
    }

    public function test_raw_source_evidence_never_appears_in_serialized_json(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Evidence Project');
        $source = Source::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'slug' => 'source-'.uniqid(),
            'name' => 'Source',
            'source_type' => 'rss',
            'base_url' => 'https://example.test',
            'feed_url' => 'https://example.test/feed',
            'feed_url_hash' => hash('sha256', uniqid('', true)),
            'allowed_domains' => ['example.test'],
            'parser_profile' => 'rss_v1',
            'enabled' => true,
            'manual_only' => false,
            'acquire_interval_seconds' => 3600,
        ]);
        Announcement::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'source_id' => $source->id,
            'identity_hash' => hash('sha256', 'https://example.test/secret-source'),
            'identity_basis' => 'canonical_url',
            'canonical_url' => 'https://example.test/secret-source',
            'raw_title' => 'Secret Source Title',
            'content_hash' => hash('sha256', 'secret'),
            'raw_payload' => ['title' => 'Secret Source Title', 'deadline' => '2099-01-01'],
            'revision_no' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
        $this->seedEligibleRecord($organization->id, $project->id, 'api-record');

        $response = $this->actingAsUser($owner)
            ->getJson("/api/v1/organizations/{$organization->id}/projects/{$project->id}/hub");

        $encoded = json_encode($response->json());
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('raw_payload', $encoded);
        $this->assertStringNotContainsString('secret-source', $encoded);
        $this->assertStringNotContainsString('Secret Source Title', $encoded);
    }

    public function test_remote_post_id_never_appears_in_serialized_json(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Remote ID Project');
        $entity = $this->seedEligibleRecord($organization->id, $project->id, 'remote-id-record');
        RemotePostBindingModel::query()
            ->where('content_entity_id', $entity->id)
            ->update(['remote_post_id' => '987654']);

        $response = $this->actingAsUser($owner)
            ->getJson("/api/v1/organizations/{$organization->id}/projects/{$project->id}/hub");

        $encoded = json_encode($response->json());
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('remote_post_id', $encoded);
        $this->assertStringNotContainsString('987654', $encoded);
    }

    private function createProject(string $organizationId, string $userId, string $name): Project
    {
        return app(ProjectService::class)->create(
            Organization::query()->findOrFail($organizationId),
            User::query()->findOrFail($userId),
            $name,
        );
    }

    private function seedEligibleRecord(
        string $organizationId,
        string $projectId,
        string $entityId = 'api-record',
    ): ContentEntityModel {
        $now = now();
        $entity = ContentEntityModel::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'entity_id' => $entityId,
            'entity_type' => 'process',
            'label' => 'API Record',
            'source_family' => 'asep',
            'thematic_categories' => ['health'],
            'content_role' => 'satellite',
            'lifecycle_status' => 'open',
            'verification_status' => 'verified',
            'last_verified_at' => $now->copy()->subDay(),
            'application_deadline_at' => $now->copy()->addDays(5),
            'hub_member' => true,
            'publish_eligible' => true,
            'archive_state' => 'active',
            'verified_announcement_id' => Str::uuid()->toString(),
            'verified_content_hash' => hash('sha256', 'verified-evidence'),
        ]);
        RemotePostBindingModel::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'content_entity_id' => $entity->id,
            'remote_system' => 'wordpress',
            'remote_post_id' => '555',
            'canonical_url' => 'https://example.test/'.$entityId,
            'confirmed_at' => $now->copy()->subDay(),
            'bound_at' => $now->copy()->subDay(),
        ]);

        app(ConfigurationService::class)->set(
            $organizationId,
            'editorial.hub_profile',
            [
                'value' => [
                    'version' => 1,
                    'hub_entity_id' => 'test-hub',
                    'hub_url' => 'https://example.test/hub/',
                    'hub_title' => 'Test Hub',
                    'stale_threshold_hours' => 168,
                ],
            ],
            $projectId,
        );

        return $entity;
    }
}
