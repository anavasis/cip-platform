<?php

namespace Tests\Feature;

use App\Domain\Shared\Enums\RoleScope;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\OrganizationMembership;
use App\Infrastructure\Persistence\Models\Role;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_another_organizations_projects(): void
    {
        ['user' => $userA, 'organization' => $orgA] = $this->createUserWithOrg();
        ['organization' => $orgB] = $this->createUserWithOrg();

        $token = $this->authToken($userA);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/organizations/{$orgB->id}/projects")
            ->assertForbidden();
    }

    public function test_user_can_access_own_organization(): void
    {
        ['user' => $user, 'organization' => $org] = $this->createUserWithOrg();
        $token = $this->authToken($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson("/api/v1/organizations/{$org->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $org->id);
    }

    public function test_member_without_project_membership_can_list_projects_via_org_permission(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $org = Organization::create([
            'name' => 'Shared Org',
            'slug' => 'shared-org',
            'created_by' => $owner->id,
        ]);

        $ownerRole = Role::where('name', 'owner')->where('scope', RoleScope::Organization)->first();
        $memberRole = Role::where('name', 'member')->where('scope', RoleScope::Organization)->first();

        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $owner->id,
            'role_id' => $ownerRole->id,
        ]);
        OrganizationMembership::create([
            'organization_id' => $org->id,
            'user_id' => $member->id,
            'role_id' => $memberRole->id,
        ]);

        $token = $this->authToken($owner);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/organizations/{$org->id}/projects", ['name' => 'Alpha'])
            ->assertCreated();

        $memberToken = $this->authToken($member);
        $this->withHeader('Authorization', 'Bearer '.$memberToken)
            ->getJson("/api/v1/organizations/{$org->id}/projects")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
