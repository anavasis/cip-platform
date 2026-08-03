<?php

namespace App\Modules\Acquisition\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Application\SourceRegistryService;
use App\Modules\Acquisition\Domain\Sources\SourceRepositoryInterface;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SourceController extends Controller
{
    public function __construct(
        private readonly SourceRepositoryInterface $sources,
        private readonly SourceRegistryService $registry,
    ) {}

    public function index(Organization $organization, Project $project): JsonResponse
    {
        return response()->json([
            'data' => $this->sources->findAll($organization->id, $project->id),
        ]);
    }

    public function store(Request $request, Organization $organization, Project $project): JsonResponse
    {
        $validated = $request->validate($this->rules(false));
        $result = $this->registry->create($organization->id, $project->id, $validated);

        if (($result['success'] ?? false) !== true) {
            return $this->errorResponse((string) ($result['error'] ?? 'source_create_failed'));
        }

        $source = $this->sources->findById(
            $organization->id,
            $project->id,
            (string) $result['id'],
        );

        return response()->json(['data' => $source], 201);
    }

    public function show(
        Organization $organization,
        Project $project,
        Source $source,
    ): JsonResponse {
        return response()->json(['data' => $source]);
    }

    public function update(
        Request $request,
        Organization $organization,
        Project $project,
        Source $source,
    ): JsonResponse {
        $validated = $request->validate($this->rules(true));
        $input = array_merge([
            'name' => $source->name,
            'source_type' => $source->source_type,
            'base_url' => $source->base_url,
            'feed_url' => $source->feed_url,
            'allowed_domains' => $source->allowed_domains ?? [],
            'parser_profile' => $source->parser_profile,
            'manual_only' => $source->manual_only,
        ], $validated);
        $result = $this->registry->update(
            $organization->id,
            $project->id,
            (string) $source->id,
            $input,
        );

        if (($result['success'] ?? false) !== true) {
            return $this->errorResponse((string) ($result['error'] ?? 'source_update_failed'));
        }

        return response()->json(['data' => $source->fresh()]);
    }

    public function destroy(
        Organization $organization,
        Project $project,
        Source $source,
    ): JsonResponse {
        $source->delete();

        return response()->json(null, 204);
    }

    public function enable(
        Organization $organization,
        Project $project,
        Source $source,
    ): JsonResponse {
        return $this->toggle($organization, $project, $source, true);
    }

    public function disable(
        Organization $organization,
        Project $project,
        Source $source,
    ): JsonResponse {
        return $this->toggle($organization, $project, $source, false);
    }

    /** @return array<string, array<int, string>> */
    private function rules(bool $partial): array
    {
        $presence = $partial ? 'sometimes' : 'required';

        return [
            'slug' => [$presence, 'string', 'max:128'],
            'name' => [$presence, 'string', 'max:191'],
            'source_type' => [$presence, 'string', 'in:rss,atom,html,json,xml,pdf,manual'],
            'base_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'feed_url' => [$presence, 'url:http,https', 'max:2048'],
            'allowed_domains' => [$presence, 'array', 'min:1'],
            'allowed_domains.*' => ['string', 'max:253'],
            'parser_profile' => ['sometimes', 'nullable', 'string', 'max:64'],
            'enabled' => ['sometimes', 'boolean'],
            'manual_only' => ['sometimes', 'boolean'],
        ];
    }

    private function toggle(
        Organization $organization,
        Project $project,
        Source $source,
        bool $enabled,
    ): JsonResponse {
        $result = $this->registry->toggle(
            $organization->id,
            $project->id,
            (string) $source->id,
            $enabled,
        );

        if (($result['success'] ?? false) !== true) {
            return $this->errorResponse((string) ($result['error'] ?? 'source_toggle_failed'));
        }

        return response()->json(['data' => $source->fresh()]);
    }

    private function errorResponse(string $errorCode): JsonResponse
    {
        $status = $errorCode === 'not_found' ? 404 : 422;

        return response()->json([
            'data' => null,
            'error' => ['code' => $errorCode],
        ], $status);
    }
}
