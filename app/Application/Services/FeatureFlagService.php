<?php

namespace App\Application\Services;

use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Infrastructure\Persistence\Models\FeatureFlag;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Collection;

class FeatureFlagService
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * @param  array<string, mixed>|null  $value
     */
    public function upsert(
        string $key,
        bool $enabled,
        FeatureFlagScope $scope,
        ?array $value = null,
        ?string $organizationId = null,
        ?string $projectId = null,
        ?User $user = null,
    ): FeatureFlag {
        $flag = FeatureFlag::updateOrCreate(
            [
                'scope' => $scope,
                'organization_id' => $organizationId,
                'project_id' => $projectId,
                'key' => $key,
            ],
            [
                'enabled' => $enabled,
                'value' => $value,
            ]
        );

        $this->audit->record('feature_flag.upserted', $user, $organizationId, $projectId, 'feature_flag', $flag->id, [
            'key' => $key,
            'enabled' => $enabled,
            'scope' => $scope->value,
        ]);

        return $flag;
    }

  /**
   * @return Collection<int, FeatureFlag>
   */
    public function list(
        ?string $organizationId = null,
        ?string $projectId = null,
    ): Collection {
        return FeatureFlag::query()
            ->where(function ($q) use ($organizationId, $projectId) {
                $q->where('scope', FeatureFlagScope::Global);

                if ($organizationId) {
                    $q->orWhere(function ($q2) use ($organizationId) {
                        $q2->where('scope', FeatureFlagScope::Organization)
                            ->where('organization_id', $organizationId);
                    });
                }

                if ($projectId) {
                    $q->orWhere(function ($q2) use ($projectId) {
                        $q2->where('scope', FeatureFlagScope::Project)
                            ->where('project_id', $projectId);
                    });
                }
            })
            ->orderBy('key')
            ->get();
    }

    public function isEnabled(
        string $key,
        ?string $organizationId = null,
        ?string $projectId = null,
    ): bool {
        if ($projectId) {
            $projectFlag = FeatureFlag::query()
                ->where('scope', FeatureFlagScope::Project)
                ->where('project_id', $projectId)
                ->where('key', $key)
                ->first();

            if ($projectFlag) {
                return $projectFlag->enabled;
            }
        }

        if ($organizationId) {
            $orgFlag = FeatureFlag::query()
                ->where('scope', FeatureFlagScope::Organization)
                ->where('organization_id', $organizationId)
                ->where('key', $key)
                ->first();

            if ($orgFlag) {
                return $orgFlag->enabled;
            }
        }

        $globalFlag = FeatureFlag::query()
            ->where('scope', FeatureFlagScope::Global)
            ->where('key', $key)
            ->first();

        return $globalFlag?->enabled ?? false;
    }
}
