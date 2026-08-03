<?php

namespace App\Application\Services;

use App\Infrastructure\Persistence\Models\OrganizationMembership;
use App\Infrastructure\Persistence\Models\ProjectMembership;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Collection;

class PermissionService
{
    public function userHasOrganizationAccess(User $user, string $organizationId): bool
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function userHasProjectAccess(User $user, string $organizationId, string $projectId): bool
    {
        if (! $this->userHasOrganizationAccess($user, $organizationId)) {
            return false;
        }

        if (ProjectMembership::query()
            ->where('project_id', $projectId)
            ->where('user_id', $user->id)
            ->exists()) {
            return true;
        }

        return OrganizationMembership::query()
            ->where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->whereHas('role.permissions', fn ($q) => $q->where('name', 'projects.view'))
            ->exists();
    }

    public function userHasPermission(
        User $user,
        string $permission,
        ?string $organizationId = null,
        ?string $projectId = null,
    ): bool {
        if ($organizationId === null && $projectId === null) {
            return $this->userHasPermissionInAnyContext($user, $permission);
        }

        $permissions = $this->resolvePermissions($user, $organizationId, $projectId);

        return $permissions->contains($permission);
    }

    public function userHasPermissionInAnyContext(User $user, string $permission): bool
    {
        $orgMemberships = OrganizationMembership::query()
            ->where('user_id', $user->id)
            ->with('role.permissions')
            ->get();

        foreach ($orgMemberships as $membership) {
            if ($membership->role->permissions->pluck('name')->contains($permission)) {
                return true;
            }
        }

        $projectMemberships = ProjectMembership::query()
            ->where('user_id', $user->id)
            ->with('role.permissions')
            ->get();

        foreach ($projectMemberships as $membership) {
            if ($membership->role->permissions->pluck('name')->contains($permission)) {
                return true;
            }
        }

        return false;
    }

  /**
   * @return Collection<int, string>
   */
    public function resolvePermissions(
        User $user,
        ?string $organizationId = null,
        ?string $projectId = null,
    ): Collection {
        $permissions = collect();

        if ($organizationId) {
            $orgMembership = OrganizationMembership::query()
                ->where('organization_id', $organizationId)
                ->where('user_id', $user->id)
                ->with('role.permissions')
                ->first();

            if ($orgMembership) {
                $permissions = $permissions->merge(
                    $orgMembership->role->permissions->pluck('name')
                );
            }
        }

        if ($organizationId && $projectId) {
            $projectMembership = ProjectMembership::query()
                ->where('project_id', $projectId)
                ->where('user_id', $user->id)
                ->with('role.permissions')
                ->first();

            if ($projectMembership) {
                $permissions = $permissions->merge(
                    $projectMembership->role->permissions->pluck('name')
                );
            }
        }

        return $permissions->unique()->values();
    }
}
