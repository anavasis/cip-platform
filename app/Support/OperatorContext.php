<?php

namespace App\Support;

use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use App\Infrastructure\Persistence\Models\User;

final class OperatorContext
{
    public const SESSION_ORG = 'operator.organization_id';

    public const SESSION_PROJECT = 'operator.project_id';

    public static function organizationId(): ?string
    {
        $id = session(self::SESSION_ORG);

        return is_string($id) && $id !== '' ? $id : null;
    }

    public static function projectId(): ?string
    {
        $id = session(self::SESSION_PROJECT);

        return is_string($id) && $id !== '' ? $id : null;
    }

    public static function set(?string $organizationId, ?string $projectId): void
    {
        if ($organizationId === null || $organizationId === '') {
            session()->forget([self::SESSION_ORG, self::SESSION_PROJECT]);

            return;
        }

        session([self::SESSION_ORG => $organizationId]);
        if ($projectId === null || $projectId === '') {
            session()->forget(self::SESSION_PROJECT);
        } else {
            session([self::SESSION_PROJECT => $projectId]);
        }
    }

    public static function organization(): ?Organization
    {
        $id = self::organizationId();

        return $id ? Organization::query()->find($id) : null;
    }

    public static function project(): ?Project
    {
        $id = self::projectId();
        $orgId = self::organizationId();
        if ($id === null || $orgId === null) {
            return null;
        }

        return Project::query()
            ->where('organization_id', $orgId)
            ->whereKey($id)
            ->first();
    }

    /**
     * @return array{user: User, organization: Organization, project: Project}
     */
    public static function requireProject(User $user): array
    {
        $organization = self::organization();
        $project = self::project();
        if ($organization === null || $project === null) {
            abort(302, '', ['Location' => route('app.context.select')]);
        }

        return [
            'user' => $user,
            'organization' => $organization,
            'project' => $project,
        ];
    }
}
