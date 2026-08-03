<?php

namespace App\Modules\Editorial\Infrastructure\Persistence\Repositories;

use App\Modules\Editorial\Domain\PromptContext\PromptContext;
use App\Modules\Editorial\Domain\PromptContext\PromptContextRepositoryInterface;
use App\Modules\Editorial\Infrastructure\Persistence\Models\PromptContextModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

final class EloquentPromptContextRepository implements PromptContextRepositoryInterface
{
    public function save(string $organizationId, string $projectId, PromptContext $context): bool
    {
        if ($organizationId === '' || $projectId === '' || $context->contextId() === '') {
            return false;
        }

        try {
            $existing = PromptContextModel::query()
                ->where('project_id', $projectId)
                ->where('context_hash', $context->contextHash())
                ->first();
            if ($existing) {
                return true;
            }

            PromptContextModel::query()->create([
                'id' => (string) Str::uuid(),
                'organization_id' => $organizationId,
                'project_id' => $projectId,
                'announcement_id' => $context->announcementId(),
                'context_id' => $context->contextId(),
                'blueprint_id' => $context->blueprintId(),
                'blueprint_revision' => $context->blueprintRevision(),
                'announcement_revision_no' => $context->announcementRevisionNo(),
                'source_content_hash' => $context->sourceContentHash(),
                'context_hash' => $context->contextHash(),
                'status' => $context->status(),
                'payload' => $context->toArray(),
            ]);

            return true;
        } catch (QueryException $e) {
            $existing = PromptContextModel::query()
                ->where('project_id', $projectId)
                ->where(function ($q) use ($context) {
                    $q->where('context_id', $context->contextId())
                        ->orWhere('context_hash', $context->contextHash());
                })
                ->first();

            return $existing !== null;
        }
    }

    public function findById(string $organizationId, string $projectId, string $contextId): ?PromptContext
    {
        $row = PromptContextModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('context_id', $contextId)
            ->first();

        return $row ? new PromptContext($row->payload ?? []) : null;
    }

    public function findByContextHash(string $organizationId, string $projectId, string $contextHash): ?PromptContext
    {
        $row = PromptContextModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('context_hash', $contextHash)
            ->first();

        return $row ? new PromptContext($row->payload ?? []) : null;
    }

    public function findLatestForAnnouncement(string $organizationId, string $projectId, string $announcementId): ?PromptContext
    {
        $row = PromptContextModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('announcement_id', $announcementId)
            ->orderByDesc('created_at')
            ->first();

        return $row ? new PromptContext($row->payload ?? []) : null;
    }

    public function findLatestForBlueprint(string $organizationId, string $projectId, string $blueprintId): ?PromptContext
    {
        $row = PromptContextModel::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId)
            ->where('blueprint_id', $blueprintId)
            ->orderByDesc('created_at')
            ->first();

        return $row ? new PromptContext($row->payload ?? []) : null;
    }
}
