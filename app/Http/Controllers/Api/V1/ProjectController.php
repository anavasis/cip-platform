<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\ProjectService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(
        private readonly ProjectService $projects,
    ) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        return response()->json([
            'data' => $this->projects->listForOrganization($organization, $request->user()),
        ]);
    }

    public function store(Request $request, Organization $organization): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $project = $this->projects->create(
            $organization,
            $request->user(),
            $validated['name'],
            $validated['slug'] ?? null,
            $validated['description'] ?? null,
        );

        return response()->json(['data' => $project], 201);
    }

    public function show(Organization $organization, Project $project): JsonResponse
    {
        return response()->json(['data' => $project]);
    }

    public function update(Request $request, Organization $organization, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
        ]);

        $updated = $this->projects->update($project, $validated, $request->user());

        return response()->json(['data' => $updated]);
    }

    public function destroy(Request $request, Organization $organization, Project $project): JsonResponse
    {
        $this->projects->delete($project, $request->user());

        return response()->json(null, 204);
    }
}
