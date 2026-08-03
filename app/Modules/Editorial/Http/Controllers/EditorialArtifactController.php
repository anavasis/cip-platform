<?php

namespace App\Modules\Editorial\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Domain\Article\ArticlePreviewRepositoryInterface;
use App\Modules\Editorial\Domain\Blueprint\ContentBlueprintRepositoryInterface;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestRepositoryInterface;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultRepositoryInterface;
use App\Modules\Editorial\Domain\PromptContext\PromptContextRepositoryInterface;
use App\Modules\Editorial\Domain\PromptPackage\PromptPackageRepositoryInterface;
use App\Modules\Editorial\Infrastructure\Persistence\Models\ContentBlueprintModel;
use App\Modules\Editorial\Infrastructure\Persistence\Models\GenerationRequestModel;
use App\Modules\Editorial\Infrastructure\Persistence\Models\GenerationResultModel;
use App\Modules\Editorial\Infrastructure\Persistence\Models\PromptContextModel;
use App\Modules\Editorial\Infrastructure\Persistence\Models\PromptPackageModel;
use Illuminate\Http\JsonResponse;

class EditorialArtifactController extends Controller
{
    public function __construct(
        private readonly ContentBlueprintRepositoryInterface $blueprints,
        private readonly PromptContextRepositoryInterface $contexts,
        private readonly PromptPackageRepositoryInterface $packages,
        private readonly GenerationRequestRepositoryInterface $requests,
        private readonly GenerationResultRepositoryInterface $results,
        private readonly ArticlePreviewRepositoryInterface $previews,
    ) {}

    public function showBlueprint(Organization $organization, Project $project, string $blueprint): JsonResponse
    {
        $row = ContentBlueprintModel::query()
            ->where('organization_id', $organization->id)
            ->where('project_id', $project->id)
            ->where('blueprint_id', $blueprint)
            ->firstOrFail();
        $domain = $this->blueprints->findById($organization->id, $project->id, $row->blueprint_id);

        return response()->json(['data' => $domain?->toArray()]);
    }

    public function showPromptContext(Organization $organization, Project $project, string $promptContext): JsonResponse
    {
        $row = PromptContextModel::query()
            ->where('organization_id', $organization->id)
            ->where('project_id', $project->id)
            ->where('context_id', $promptContext)
            ->firstOrFail();
        $domain = $this->contexts->findById($organization->id, $project->id, $row->context_id);
        $payload = $domain?->toArray() ?? [];
        // metadata only — strip facts summary/body-like fields beyond structure
        if (isset($payload['facts']) && is_array($payload['facts'])) {
            unset($payload['facts']['summary_text'], $payload['facts']['key_facts']);
        }

        return response()->json(['data' => [
            'context_id' => $payload['context_id'] ?? null,
            'announcement_id' => $payload['announcement_id'] ?? null,
            'blueprint_id' => $payload['blueprint_id'] ?? null,
            'context_hash' => $payload['context_hash'] ?? null,
            'status' => $payload['status'] ?? null,
            'source_content_hash' => $payload['source_content_hash'] ?? null,
            'created_at_utc' => $payload['created_at_utc'] ?? null,
        ]]);
    }

    public function showPromptPackage(Organization $organization, Project $project, string $promptPackage): JsonResponse
    {
        $row = PromptPackageModel::query()
            ->where('organization_id', $organization->id)
            ->where('project_id', $project->id)
            ->where('package_id', $promptPackage)
            ->firstOrFail();
        $domain = $this->packages->findById($organization->id, $project->id, $row->package_id);
        $payload = $domain?->toArray() ?? [];

        return response()->json(['data' => [
            'package_id' => $payload['package_id'] ?? null,
            'announcement_id' => $payload['announcement_id'] ?? null,
            'context_id' => $payload['context_id'] ?? null,
            'package_hash' => $payload['package_hash'] ?? null,
            'status' => $payload['status'] ?? null,
            'template_reference' => $payload['template_reference'] ?? null,
            'blueprint_reference' => $payload['blueprint_reference'] ?? null,
            'sealed_at_utc' => $payload['sealed_at_utc'] ?? null,
        ]]);
    }

    public function showGenerationRequest(Organization $organization, Project $project, string $generationRequest): JsonResponse
    {
        $row = GenerationRequestModel::query()
            ->where('organization_id', $organization->id)
            ->where('project_id', $project->id)
            ->where('request_id', $generationRequest)
            ->firstOrFail();
        $domain = $this->requests->findById($organization->id, $project->id, $row->request_id);

        return response()->json(['data' => $domain?->toArray()]);
    }

    public function showGenerationResult(Organization $organization, Project $project, string $generationResult): JsonResponse
    {
        $row = GenerationResultModel::query()
            ->where('organization_id', $organization->id)
            ->where('project_id', $project->id)
            ->where('result_id', $generationResult)
            ->firstOrFail();
        $domain = $this->results->findById($organization->id, $project->id, $row->result_id);
        $payload = $domain?->toArray() ?? [];
        unset($payload['error_message']);

        return response()->json(['data' => $payload]);
    }

    public function showPreview(
        Organization $organization,
        Project $project,
        Announcement $announcement,
    ): JsonResponse {
        $preview = $this->previews->findLatestForAnnouncement(
            $organization->id,
            $project->id,
            $announcement->id,
        );
        if ($preview === null) {
            return response()->json(['message' => 'not_found'], 404);
        }

        return response()->json(['data' => [
            'preview_id' => $preview->previewId(),
            'announcement_id' => $preview->announcementId(),
            'request_id' => $preview->requestId(),
            'result_id' => $preview->resultId(),
            'title' => $preview->title(),
            'body' => $preview->body(),
            'created_at_utc' => $preview->createdAtUtc(),
        ]]);
    }
}
