<?php

namespace App\Application\Services;

use App\Domain\Events\OrganizationCreated;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\OrganizationMembership;
use App\Infrastructure\Persistence\Models\Role;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Str;

class OrganizationService
{
    public function __construct(
        private readonly EventBusService $eventBus,
        private readonly AuditService $audit,
    ) {}

    public function create(User $user, string $name, ?string $slug = null): Organization
    {
        $slug = $slug ?? Str::slug($name).'-'.Str::random(6);

        $organization = Organization::create([
            'name' => $name,
            'slug' => $slug,
            'created_by' => $user->id,
        ]);

        $ownerRole = Role::query()
            ->where('name', 'owner')
            ->where('scope', 'organization')
            ->firstOrFail();

        OrganizationMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role_id' => $ownerRole->id,
        ]);

        $this->eventBus->dispatch(new OrganizationCreated(
            $organization->id,
            $organization->name,
            $user->id,
        ));

        $this->audit->record('organization.created', $user, $organization->id, resourceType: 'organization', resourceId: $organization->id);

        return $organization;
    }

    public function update(Organization $organization, array $data, User $user): Organization
    {
        $organization->update($data);
        $this->audit->record('organization.updated', $user, $organization->id, resourceType: 'organization', resourceId: $organization->id);

        return $organization->fresh();
    }

    public function delete(Organization $organization, User $user): void
    {
        $orgId = $organization->id;
        $organization->delete();
        $this->audit->record('organization.deleted', $user, $orgId, resourceType: 'organization', resourceId: $orgId);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Organization>
     */
    public function listForUser(User $user)
    {
        return Organization::query()
            ->whereHas('memberships', fn ($q) => $q->where('user_id', $user->id))
            ->orderBy('name')
            ->get();
    }

    public function addMember(Organization $organization, User $member, string $roleName, User $actor): OrganizationMembership
    {
        $role = Role::query()
            ->where('name', $roleName)
            ->where('scope', 'organization')
            ->firstOrFail();

        $membership = OrganizationMembership::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'user_id' => $member->id,
            ],
            ['role_id' => $role->id]
        );

        $this->audit->record('organization.member.assigned', $actor, $organization->id, metadata: [
            'member_id' => $member->id,
            'role' => $roleName,
        ]);

        return $membership->load('role', 'user');
    }

    public function listMembers(Organization $organization)
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->with(['user', 'role'])
            ->get();
    }
}
