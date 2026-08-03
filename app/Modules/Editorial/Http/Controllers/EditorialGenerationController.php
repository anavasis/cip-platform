<?php

namespace App\Modules\Editorial\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Application\GenerateArticlePreviewService;
use App\Modules\Editorial\Domain\GenerationRequest\GenerationRequestRepositoryInterface;
use App\Modules\Editorial\Domain\GenerationResult\GenerationResultRepositoryInterface;
use App\Modules\Editorial\Http\Requests\GenerateArticlePreviewRequest;
use App\Modules\Editorial\Http\Resources\GenerationStatusResource;
use App\Modules\Editorial\Infrastructure\Persistence\Models\GenerationRequestModel;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class EditorialGenerationController extends Controller
{
    public function __construct(
        private readonly GenerateArticlePreviewService $service,
        private readonly GenerationRequestRepositoryInterface $requests,
        private readonly GenerationResultRepositoryInterface $results,
    ) {}

    public function generate(
        GenerateArticlePreviewRequest $request,
        Organization $organization,
        Project $project,
        Announcement $announcement,
    ): JsonResponse {
        return $this->run($request, $organization, $project, $announcement, false);
    }

    public function regenerate(
        GenerateArticlePreviewRequest $request,
        Organization $organization,
        Project $project,
        Announcement $announcement,
    ): JsonResponse {
        return $this->run($request, $organization, $project, $announcement, true);
    }

    public function showGeneration(
        Organization $organization,
        Project $project,
        Announcement $announcement,
    ): JsonResponse {
        $request = $this->requests->findLatestForAnnouncement(
            $organization->id,
            $project->id,
            $announcement->id,
        );
        $result = $request
            ? $this->results->findByRequestId($organization->id, $project->id, $request->requestId())
            : null;

        return response()->json([
            'data' => [
                'announcement_id' => $announcement->id,
                'request' => $request?->toArray(),
                'result' => $result ? $this->safeResult($result->toArray()) : null,
            ],
        ]);
    }

    public function indexGenerations(
        Organization $organization,
        Project $project,
    ): JsonResponse {
        $perPage = min(100, max(1, (int) request()->integer('per_page', 25)));
        $page = max(1, (int) request()->integer('page', 1));
        $offset = ($page - 1) * $perPage;

        $items = $this->requests->listForProject($organization->id, $project->id, $perPage, $offset);
        $total = GenerationRequestModel::query()
            ->where('organization_id', $organization->id)
            ->where('project_id', $project->id)
            ->count();

        return response()->json([
            'data' => array_map(fn ($r) => $r->toArray(), $items),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    private function run(
        GenerateArticlePreviewRequest $request,
        Organization $organization,
        Project $project,
        Announcement $announcement,
        bool $regenerate,
    ): JsonResponse {
        try {
            $result = $this->service->generate(
                $organization->id,
                $project->id,
                $announcement->id,
                $request->user()?->id,
                $request->validated('correlation_id'),
                $regenerate,
                (bool) ($request->validated('async') ?? false),
            );
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            $status = match ($code) {
                'capability_disabled' => 403,
                'announcement_not_found' => 404,
                'announcement_locked' => 409,
                default => 422,
            };

            return response()->json(['message' => $code, 'error' => $code], $status);
        }

        return response()->json(['data' => $result], ($result['queued'] ?? false) ? 202 : 200);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function safeResult(array $result): array
    {
        unset($result['error_message']);

        return $result;
    }
}
