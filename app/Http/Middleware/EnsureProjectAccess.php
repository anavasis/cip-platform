<?php

namespace App\Http\Middleware;

use App\Application\Services\PermissionService;
use App\Domain\Shared\Exceptions\ForbiddenException;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectAccess
{
    public function __construct(
        private readonly PermissionService $permissions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organization = $request->route('organization');
        $project = $request->route('project');

        $organizationId = $organization instanceof Organization
            ? $organization->id
            : (string) $organization;

        $projectId = $project instanceof Project
            ? $project->id
            : (string) $project;

        if (! $user || ! $this->permissions->userHasProjectAccess($user, $organizationId, $projectId)) {
            throw new ForbiddenException('You do not have access to this project.');
        }

        $request->attributes->set('organization_id', $organizationId);
        $request->attributes->set('project_id', $projectId);

        return $next($request);
    }
}
