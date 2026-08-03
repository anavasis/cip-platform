<?php

namespace App\Http\Middleware;

use App\Application\Services\PermissionService;
use App\Domain\Shared\Exceptions\ForbiddenException;
use App\Infrastructure\Persistence\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganizationAccess
{
    public function __construct(
        private readonly PermissionService $permissions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $organization = $request->route('organization');

        if ($organization instanceof Organization) {
            $organizationId = $organization->id;
        } else {
            $organizationId = (string) $organization;
        }

        if (! $user || ! $this->permissions->userHasOrganizationAccess($user, $organizationId)) {
            throw new ForbiddenException('You do not have access to this organization.');
        }

        $request->attributes->set('organization_id', $organizationId);

        return $next($request);
    }
}
