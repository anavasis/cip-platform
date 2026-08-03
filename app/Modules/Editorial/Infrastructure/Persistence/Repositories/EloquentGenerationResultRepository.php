<?php

namespace App\Modules\Editorial\Infrastructure\Persistence\Repositories;

use App\Modules\Editorial\Domain\GenerationResult\GenerationResult;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultRepositoryInterface;
use App\Modules\Editorial\Infrastructure\Persistence\Models\GenerationResultModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

final class EloquentGenerationResultRepository implements GenerationResultRepositoryInterface
{
    public function save(string $organizationId, string $projectId, GenerationResult $result): bool
    {
        if ($organizationId === '' || $projectId === '' || $result->resultId() === '') {
            return false;
        }

        try {
            $byHash = GenerationResultModel::query()
                ->where('project_id', $projectId)
                ->where('result_hash', $result->resultHash())
                ->first();
            if ($byHash) {
                return true;
            }

            GenerationResultModel::query()->create([
                'id' => (string) Str::uuid(),
                'organization_id' => $organizationId,
                'project_id' => $projectId,
                'announcement_id' => $result->announcementId(),
                'result_id' => $result->resultId(),
                'request_id' => $result->requestId(),
                'request_hash' => $result->requestHash(),
                'package_id' => $result->packageId(),
                'package_hash' => $result->packageHash(),
                'result_hash' => $result->resultHash(),
                'status' => $result->status(),
                'provider_code' => $result->providerExecution()->providerCode() !== ''
                    ? $result->providerExecution()->providerCode()
                    : null,
                'execution_id' => $result->providerExecution()->executionId() !== ''
                    ? $result->providerExecution()->executionId()
                    : null,
                'error_code' => $result->errorCode() !== '' ? $result->errorCode() : null,
                'duration_ms' => $result->durationMs(),
                'payload' => $result->toArray(),
            ]);

            return true;
        } catch (QueryException) {
            return GenerationResultModel::query()
                ->where('project_id', $projectId)
                ->where(function ($q) use ($result) {
                    $q->where('result_id', $result->resultId())
                        ->orWhere('result_hash', $result->resultHash());
                })
                ->exists();
        }
    }

    public function findById(string $organizationId, string $projectId, string $resultId): ?GenerationResult
    {
        $row = GenerationResultModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('result_id', $resultId)
            ->first();

        return $row ? new GenerationResult($row->payload ?? []) : null;
    }

    public function findByResultHash(string $organizationId, string $projectId, string $resultHash): ?GenerationResult
    {
        $row = GenerationResultModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('result_hash', $resultHash)
            ->first();

        return $row ? new GenerationResult($row->payload ?? []) : null;
    }

    public function findByRequestId(string $organizationId, string $projectId, string $requestId): ?GenerationResult
    {
        $row = GenerationResultModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('request_id', $requestId)
            ->orderByDesc('created_at')
            ->first();

        return $row ? new GenerationResult($row->payload ?? []) : null;
    }

    public function findLatestForAnnouncement(string $organizationId, string $projectId, string $announcementId): ?GenerationResult
    {
        $row = GenerationResultModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('announcement_id', $announcementId)
            ->orderByDesc('created_at')
            ->first();

        return $row ? new GenerationResult($row->payload ?? []) : null;
    }
}
