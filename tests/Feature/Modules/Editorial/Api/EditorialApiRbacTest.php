<?php

namespace Tests\Feature\Modules\Editorial\Api;

use App\Application\Services\FeatureFlagService;
use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Domain\Shared\Enums\RoleScope;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\OrganizationMembership;
use App\Infrastructure\Persistence\Models\Project;
use App\Infrastructure\Persistence\Models\ProjectMembership;
use App\Infrastructure\Persistence\Models\Role;
use App\Infrastructure\Persistence\Models\User;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Application\CapabilityGate;
use Illuminate\Support\Str;
use Tests\TestCase;

class EditorialApiRbacTest extends TestCase
{
    public function test_unauthenticated_denied(): void
    {
        $response = $this->postJson('/api/v1/organizations/'.Str::uuid().'/projects/'.Str::uuid().'/editorial/announcements/'.Str::uuid().'/generate');
        $response->assertUnauthorized();
    }

    public function test_generate_and_preview_happy_path_with_permissions(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'API Project');
        $source = $this->createSource($organization->id, $project->id, 'api-source');
        $ann = $this->createAnnouncement($organization->id, $project->id, $source->id, 'API Title');
        $this->enableEditorial($organization->id, $project->id);

        $this->actingAsUser($owner)
            ->postJson($this->url($organization, $project, $ann, 'generate'))
            ->assertOk()
            ->assertJsonPath('data.ok', true);

        $this->actingAsUser($owner)
            ->getJson($this->url($organization, $project, $ann, 'preview'))
            ->assertOk()
            ->assertJsonPath('data.title', 'API Title');

        $this->actingAsUser($owner)
            ->getJson("/api/v1/organizations/{$organization->id}/projects/{$project->id}/editorial/diagnostics")
            ->assertOk();

        $this->actingAsUser($owner)
            ->getJson("/api/v1/organizations/{$organization->id}/projects/{$project->id}/editorial/generations?per_page=10")
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['page', 'per_page', 'total']]);
    }

    public function test_wrong_organization_and_project_denied(): void
    {
        ['user' => $ownerA, 'organization' => $orgA] = $this->createUserWithOrg();
        $projectA = $this->createProject($orgA->id, $ownerA->id, 'A');
        $sourceA = $this->createSource($orgA->id, $projectA->id, 'a-source');
        $annA = $this->createAnnouncement($orgA->id, $projectA->id, $sourceA->id, 'A Title');
        $this->enableEditorial($orgA->id, $projectA->id);

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
        $projectB = $this->createProject($orgB->id, $userB->id, 'B');

        $this->actingAsUser($userB)
            ->postJson($this->url($orgA, $projectA, $annA, 'generate'))
            ->assertForbidden();

        $this->actingAsUser($ownerA)
            ->getJson("/api/v1/organizations/{$orgA->id}/projects/{$projectB->id}/editorial/diagnostics")
            ->assertNotFound();
    }

    public function test_missing_permission_denied(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Perm Project');
        $source = $this->createSource($organization->id, $project->id, 'perm-source');
        $ann = $this->createAnnouncement($organization->id, $project->id, $source->id, 'Perm Title');
        $this->enableEditorial($organization->id, $project->id);

        $this->actingAsUser($owner)
            ->postJson($this->url($organization, $project, $ann, 'generate'))
            ->assertOk();

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
            ->getJson($this->url($organization, $project, $ann, 'preview'))
            ->assertOk(); // editorial.view

        $this->actingAsUser($viewer)
            ->postJson($this->url($organization, $project, $ann, 'generate'))
            ->assertForbidden();

        $this->actingAsUser($viewer)
            ->postJson($this->url($organization, $project, $ann, 'regenerate'))
            ->assertForbidden();

        $this->actingAsUser($viewer)
            ->getJson("/api/v1/organizations/{$organization->id}/projects/{$project->id}/editorial/diagnostics")
            ->assertForbidden();
    }

    public function test_capability_fail_closed_on_api_path(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $project = $this->createProject($organization->id, $owner->id, 'Cap API');
        $source = $this->createSource($organization->id, $project->id, 'cap-api-source');
        $ann = $this->createAnnouncement($organization->id, $project->id, $source->id, 'Cap API Title');

        $this->actingAsUser($owner)
            ->postJson($this->url($organization, $project, $ann, 'generate'))
            ->assertForbidden()
            ->assertJsonPath('error', 'capability_disabled');
    }

    public function test_cross_project_announcement_cannot_generate(): void
    {
        ['user' => $owner, 'organization' => $organization] = $this->createUserWithOrg();
        $projectA = $this->createProject($organization->id, $owner->id, 'Cross A');
        $projectB = $this->createProject($organization->id, $owner->id, 'Cross B');
        $sourceA = $this->createSource($organization->id, $projectA->id, 'cross-a');
        $annA = $this->createAnnouncement($organization->id, $projectA->id, $sourceA->id, 'Cross Title');
        $this->enableEditorial($organization->id, $projectB->id);

        $this->actingAsUser($owner)
            ->postJson("/api/v1/organizations/{$organization->id}/projects/{$projectB->id}/editorial/announcements/{$annA->id}/generate")
            ->assertNotFound();
    }

    private function url(Organization $organization, Project $project, Announcement $announcement, string $action): string
    {
        return "/api/v1/organizations/{$organization->id}/projects/{$project->id}/editorial/announcements/{$announcement->id}/{$action}";
    }

    private function enableEditorial(string $organizationId, string $projectId): void
    {
        $flags = app(FeatureFlagService::class);
        foreach ([CapabilityGate::EDITORIAL, CapabilityGate::EDITORIAL_GENERATION] as $key) {
            $flags->upsert($key, true, FeatureFlagScope::Project, null, $organizationId, $projectId);
        }
    }

    private function createProject(string $organizationId, string $userId, string $name): Project
    {
        return Project::create([
            'organization_id' => $organizationId,
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'created_by' => $userId,
        ]);
    }

    private function createSource(string $organizationId, string $projectId, string $slug): Source
    {
        $feedUrl = "https://example.com/{$slug}.xml";

        return Source::create([
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'slug' => $slug,
            'name' => $slug,
            'source_type' => 'rss',
            'base_url' => 'https://example.com',
            'feed_url' => $feedUrl,
            'feed_url_hash' => hash('sha256', $feedUrl),
            'allowed_domains' => ['example.com'],
            'enabled' => true,
            'manual_only' => false,
            'acquire_interval_seconds' => 3600,
        ]);
    }

    private function createAnnouncement(string $organizationId, string $projectId, string $sourceId, string $title): Announcement
    {
        return Announcement::create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organizationId,
            'project_id' => $projectId,
            'source_id' => $sourceId,
            'identity_hash' => hash('sha256', $title.'|'.$projectId.uniqid()),
            'identity_basis' => 'canonical_url',
            'canonical_url' => 'https://example.com/'.Str::slug($title).'-'.uniqid(),
            'raw_title' => $title,
            'content_hash' => hash('sha256', $title.uniqid()),
            'raw_payload' => ['title' => $title, 'summary' => $title.' summary'],
            'revision_no' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }
}
