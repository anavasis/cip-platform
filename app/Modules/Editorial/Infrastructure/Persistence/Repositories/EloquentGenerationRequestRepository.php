<?php

namespace App\Modules\Editorial\Infrastructure\Persistence\Repositories;

use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequest;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestRepositoryInterface;
use App\Modules\Editorial\Infrastructure\Persistence\Models\GenerationRequestModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

final class EloquentGenerationRequestRepository implements GenerationRequestRepositoryInterface
{
    public function save(string $organizationId, string $projectId, GenerationRequest $request): bool
    {
        if ($organizationId === '' || $projectId === '' || $request->requestId() === '') {
            return false;
        }

        try {
            $byHash = GenerationRequestModel::query()
                ->where('project_id', $projectId)
                ->where('request_hash', $request->requestHash())
                ->first();
            if ($byHash) {
                return true;
            }

            GenerationRequestModel::query()->create([
                'id' => (string) Str::uuid(),
                'organization_id' => $organizationId,
                'project_id' => $projectId,
                'announcement_id' => $request->announcementId(),
                'request_id' => $request->requestId(),
                'package_id' => $request->packageId(),
                'package_hash' => $request->packageHash(),
                'request_hash' => $request->requestHash(),
                'lineage_id' => $request->lineageId() !== '' ? $request->lineageId() : null,
                'status' => $request->status(),
                'model_id' => $request->modelReference()->modelId(),
                'model_version' => $request->modelReference()->modelVersion(),
                'payload' => $request->toArray(),
            ]);

            return true;
        } catch (QueryException) {
            return GenerationRequestModel::query()
                ->where('project_id', $projectId)
                ->where(function ($q) use ($request) {
                    $q->where('request_id', $request->requestId())
                        ->orWhere('request_hash', $request->requestHash());
                })
                ->exists();
        }
    }

    public function findById(string $organizationId, string $projectId, string $requestId): ?GenerationRequest
    {
        $row = GenerationRequestModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('request_id', $requestId)
            ->first();

        return $row ? new GenerationRequest($row->payload ?? []) : null;
    }

    public function findByRequestHash(string $organizationId, string $projectId, string $requestHash): ?GenerationRequest
    {
        $row = GenerationRequestModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('request_hash', $requestHash)
            ->first();

        return $row ? new GenerationRequest($row->payload ?? []) : null;
    }

    public function findLatestForAnnouncement(string $organizationId, string $projectId, string $announcementId): ?GenerationRequest
    {
        $row = GenerationRequestModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('announcement_id', $announcementId)
            ->orderByDesc('created_at')
            ->first();

        return $row ? new GenerationRequest($row->payload ?? []) : null;
    }

    public function findLatestForPackage(string $organizationId, string $projectId, string $packageId): ?GenerationRequest
    {
        $row = GenerationRequestModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('package_id', $packageId)
            ->orderByDesc('created_at')
            ->first();

        return $row ? new GenerationRequest($row->payload ?? []) : null;
    }

    public function listForProject(string $organizationId, string $projectId, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        return GenerationRequestModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($row) => new GenerationRequest($row->payload ?? []))
            ->all();
    }
}
