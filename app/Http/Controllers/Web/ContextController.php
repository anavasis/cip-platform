<?php

namespace App\Http\Controllers\Web;

use App\Application\Services\PermissionService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use App\Support\OperatorContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContextController extends Controller
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function select(Request $request): View
    {
        $user = $request->user();
        $orgIds = $user->organizationMemberships()->pluck('organization_id');
        $organizations = Organization::query()->whereIn('id', $orgIds)->orderBy('name')->get();

        return view('app.context-select', [
            'organizations' => $organizations,
            'projectsByOrg' => Project::query()->whereIn('organization_id', $orgIds)->orderBy('name')->get()->groupBy('organization_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'uuid'],
            'project_id' => ['required', 'uuid'],
        ]);
        $user = $request->user();
        if (! $this->permissions->userHasProjectAccess($user, $validated['organization_id'], $validated['project_id'])) {
            return back()->withErrors(['project_id' => 'Access denied.']);
        }
        OperatorContext::set($validated['organization_id'], $validated['project_id']);

        return redirect()->route('app.dashboard');
    }
}
