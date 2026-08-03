<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\ConnectorRegistryService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectorController extends Controller
{
    public function __construct(
        private readonly ConnectorRegistryService $connectors,
    ) {}

    public function types(): JsonResponse
    {
        return response()->json(['data' => $this->connectors->listTypes()]);
    }

    public function registerType(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $type = $this->connectors->registerType(
            $validated['type'],
            $validated['name'],
            $validated['description'] ?? null,
            $validated['metadata'] ?? null,
        );

        return response()->json(['data' => $type], 201);
    }

    public function index(Organization $organization, Project $project): JsonResponse
    {
        return response()->json([
            'data' => $this->connectors->listProjectConnectors($project->id),
        ]);
    }

    public function attach(Request $request, Organization $organization, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'connector_type_id' => ['required', 'uuid', 'exists:connector_types,id'],
            'name' => ['required', 'string', 'max:255'],
            'config' => ['sometimes', 'nullable', 'array'],
        ]);

        $connector = $this->connectors->attachToProject(
            $organization->id,
            $project->id,
            $validated['connector_type_id'],
            $validated['name'],
            $validated['config'] ?? null,
        );

        return response()->json(['data' => $connector->load('connectorType')], 201);
    }
}
