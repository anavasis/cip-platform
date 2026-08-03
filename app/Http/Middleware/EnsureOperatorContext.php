<?php

namespace App\Http\Middleware;

use App\Application\Services\PermissionService;
use App\Domain\Shared\Enums\PlatformJobStatus;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\PlatformJob;
use App\Infrastructure\Persistence\Models\Project;
use App\Support\OperatorContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOperatorContext
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        $organization = OperatorContext::organization();
        $project = OperatorContext::project();

        if ($organization === null || $project === null) {
            return redirect()->route('app.context.select');
        }

        if (! $this->permissions->userHasOrganizationAccess($user, $organization->id)
            || ! $this->permissions->userHasProjectAccess($user, $organization->id, $project->id)) {
            OperatorContext::set(null, null);

            return redirect()->route('app.context.select')
                ->with('error', 'You no longer have access to the selected organization or project.');
        }

        $request->attributes->set('organization_id', $organization->id);
        $request->attributes->set('project_id', $project->id);
        $request->attributes->set('organization', $organization);
        $request->attributes->set('project', $project);

        $orgIds = $user->organizationMemberships()->pluck('organization_id');
        $organizations = Organization::query()->whereIn('id', $orgIds)->orderBy('name')->get();
        $projects = Project::query()->whereIn('organization_id', $orgIds)->orderBy('name')->get();

        view()->share('currentOrganization', $organization);
        view()->share('currentProject', $project);
        view()->share('operatorOrganizations', $organizations);
        view()->share('operatorProjects', $projects);
        view()->share('operatorNotifications', PlatformJob::query()
            ->where('organization_id', $organization->id)
            ->where('project_id', $project->id)
            ->where('status', PlatformJobStatus::Failed)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get());

        return $next($request);
    }
}
