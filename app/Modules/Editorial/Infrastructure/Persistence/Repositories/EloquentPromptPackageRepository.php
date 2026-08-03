<?php

namespace App\Modules\Editorial\Infrastructure\Persistence\Repositories;

use App\Modules\Editorial\Domain\PromptPackage\PromptPackage;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageRepositoryInterface;
use App\Modules\Editorial\Infrastructure\Persistence\Models\PromptPackageModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

final class EloquentPromptPackageRepository implements PromptPackageRepositoryInterface
{
    public function save(string $organizationId, string $projectId, PromptPackage $package): bool
    {
        if ($organizationId === '' || $projectId === '' || $package->packageId() === '') {
            return false;
        }

        try {
            $existing = PromptPackageModel::query()
                ->where('project_id', $projectId)
                ->where('package_hash', $package->packageHash())
                ->first();
            if ($existing) {
                return true;
            }

            PromptPackageModel::query()->create([
                'id' => (string) Str::uuid(),
                'organization_id' => $organizationId,
                'project_id' => $projectId,
                'announcement_id' => $package->announcementId(),
                'package_id' => $package->packageId(),
                'context_id' => $package->contextId(),
                'context_hash' => $package->contextHash(),
                'blueprint_id' => $package->blueprintReference()->blueprintId(),
                'package_hash' => $package->packageHash(),
                'status' => $package->status(),
                'template_id' => $package->templateReference()->templateId(),
                'template_version' => $package->templateReference()->templateVersion(),
                'sealed_at_utc' => $package->sealedAtUtc() !== '' ? $package->sealedAtUtc() : null,
                'payload' => $package->toArray(),
            ]);

            return true;
        } catch (QueryException) {
            $existing = PromptPackageModel::query()
                ->where('project_id', $projectId)
                ->where(function ($q) use ($package) {
                    $q->where('package_id', $package->packageId())
                        ->orWhere('package_hash', $package->packageHash());
                })
                ->first();

            return $existing !== null;
        }
    }

    public function findById(string $organizationId, string $projectId, string $packageId): ?PromptPackage
    {
        $row = PromptPackageModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('package_id', $packageId)
            ->first();

        return $row ? new PromptPackage($row->payload ?? []) : null;
    }

    public function findByPackageHash(string $organizationId, string $projectId, string $packageHash): ?PromptPackage
    {
        $row = PromptPackageModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('package_hash', $packageHash)
            ->first();

        return $row ? new PromptPackage($row->payload ?? []) : null;
    }

    public function findLatestForAnnouncement(string $organizationId, string $projectId, string $announcementId): ?PromptPackage
    {
        $row = PromptPackageModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('announcement_id', $announcementId)
            ->orderByDesc('created_at')
            ->first();

        return $row ? new PromptPackage($row->payload ?? []) : null;
    }

    public function findLatestForContext(string $organizationId, string $projectId, string $contextId): ?PromptPackage
    {
        $row = PromptPackageModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('context_id', $contextId)
            ->orderByDesc('created_at')
            ->first();

        return $row ? new PromptPackage($row->payload ?? []) : null;
    }
}
