<?php

namespace App\Http\Middleware;

use App\Application\Services\PermissionService;
use App\Domain\Shared\Exceptions\ForbiddenException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePermission
{
    public function __construct(
        private readonly PermissionService $permissions,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            throw new ForbiddenException('Authentication required.');
        }

        $organizationId = $request->attributes->get('organization_id')
            ?? $request->route('organization')?->id
            ?? $request->route('organization');

        $projectId = $request->attributes->get('project_id')
            ?? $request->route('project')?->id
            ?? $request->route('project');

        if (! $this->permissions->userHasPermission($user, $permission, $organizationId, $projectId)) {
            throw new ForbiddenException("Missing required permission: {$permission}");
        }

        return $next($request);
    }
}
