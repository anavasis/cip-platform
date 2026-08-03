<?php

namespace App\Application\Services;

use App\Domain\Events\ProjectCreated;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use App\Infrastructure\Persistence\Models\ProjectMembership;
use App\Infrastructure\Persistence\Models\Role;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Str;

class ProjectService
{
    public function __construct(
        private readonly EventBusService $eventBus,
        private readonly AuditService $audit,
    ) {}

    public function create(
        Organization $organization,
        User $user,
        string $name,
        ?string $slug = null,
        ?string $description = null,
    ): Project {
        $slug = $slug ?? Str::slug($name);

        $project = Project::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'created_by' => $user->id,
        ]);

        $adminRole = Role::query()
            ->where('name', 'admin')
            ->where('scope', 'project')
            ->firstOrFail();

        ProjectMembership::create([
            'organization_id' => $organization->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'role_id' => $adminRole->id,
        ]);

        $this->eventBus->dispatch(new ProjectCreated(
            $project->id,
            $organization->id,
            $project->name,
            $user->id,
        ));

        $this->audit->record(
            'project.created',
            $user,
            $organization->id,
            $project->id,
            'project',
            $project->id,
        );

        return $project;
    }

    public function update(Project $project, array $data, User $user): Project
    {
        $project->update($data);
        $this->audit->record('project.updated', $user, $project->organization_id, $project->id, 'project', $project->id);

        return $project->fresh();
    }

    public function delete(Project $project, User $user): void
    {
        $orgId = $project->organization_id;
        $projectId = $project->id;
        $project->delete();
        $this->audit->record('project.deleted', $user, $orgId, $projectId, 'project', $projectId);
    }

  /**
   * @return \Illuminate\Database\Eloquent\Collection<int, Project>
   */
    public function listForOrganization(Organization $organization, User $user)
    {
        return Project::query()
            ->where('organization_id', $organization->id)
            ->orderBy('name')
            ->get();
    }

    public function addMember(Project $project, User $member, string $roleName, User $actor): ProjectMembership
    {
        $role = Role::query()
            ->where('name', $roleName)
            ->where('scope', 'project')
            ->firstOrFail();

        $membership = ProjectMembership::updateOrCreate(
            [
                'project_id' => $project->id,
                'user_id' => $member->id,
            ],
            [
                'organization_id' => $project->organization_id,
                'role_id' => $role->id,
            ]
        );

        $this->audit->record('project.member.assigned', $actor, $project->organization_id, $project->id, metadata: [
            'member_id' => $member->id,
            'role' => $roleName,
        ]);

        return $membership->load('role', 'user');
    }

    public function listMembers(Project $project)
    {
        return ProjectMembership::query()
            ->where('project_id', $project->id)
            ->with(['user', 'role'])
            ->get();
    }
}
