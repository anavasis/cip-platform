<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\ConfigurationService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigurationController extends Controller
{
    public function __construct(
        private readonly ConfigurationService $config,
    ) {}

    public function index(Request $request, Organization $organization, ?Project $project = null): JsonResponse
    {
        $projectId = $project?->id ?? $request->query('project_id');

        return response()->json([
            'data' => $this->config->list($organization->id, $projectId),
        ]);
    }

    public function show(Request $request, Organization $organization, string $key, ?Project $project = null): JsonResponse
    {
        $projectId = $project?->id ?? $request->query('project_id');
        $entry = $this->config->get($organization->id, $key, $projectId);

        if (! $entry) {
            return response()->json(['message' => 'Configuration key not found.'], 404);
        }

        return response()->json(['data' => $entry]);
    }

    public function store(Request $request, Organization $organization, ?Project $project = null): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'value' => ['required', 'array'],
            'project_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $projectId = $project?->id ?? ($validated['project_id'] ?? null);

        $entry = $this->config->set(
            $organization->id,
            $validated['key'],
            $validated['value'],
            $projectId,
            $request->user(),
        );

        return response()->json(['data' => $entry], 201);
    }
}
