<?php

namespace App\Http\Middleware;

use App\Application\Services\PermissionService;
use App\Support\OperatorContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebPermission
{
    public function __construct(private readonly PermissionService $permissions) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        $organizationId = OperatorContext::organizationId();
        $projectId = OperatorContext::projectId();

        if ($user === null || $organizationId === null) {
            abort(403);
        }

        if (! $this->permissions->userHasPermission($user, $permission, $organizationId, $projectId)) {
            abort(403, 'Missing permission: '.$permission);
        }

        return $next($request);
    }
}
