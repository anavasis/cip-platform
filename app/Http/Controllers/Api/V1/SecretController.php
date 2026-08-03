<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\SecretService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use App\Infrastructure\Persistence\Models\Secret;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecretController extends Controller
{
    public function __construct(
        private readonly SecretService $secrets,
    ) {}

    public function index(Request $request, Organization $organization, ?Project $project = null): JsonResponse
    {
        $projectId = $project?->id ?? $request->query('project_id');
        $items = $this->secrets->list($organization->id, $projectId)->map(fn (Secret $s) => [
            'id' => $s->id,
            'organization_id' => $s->organization_id,
            'project_id' => $s->project_id,
            'key' => $s->key,
            'value' => $this->secrets->maskValue(),
            'created_at' => $s->created_at,
            'updated_at' => $s->updated_at,
        ]);

        return response()->json(['data' => $items]);
    }

    public function store(Request $request, Organization $organization, ?Project $project = null): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string'],
            'project_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $projectId = $project?->id ?? ($validated['project_id'] ?? null);

        $secret = $this->secrets->create(
            $organization->id,
            $validated['key'],
            $validated['value'],
            $projectId,
            $request->user(),
        );

        return response()->json(['data' => [
            'id' => $secret->id,
            'organization_id' => $secret->organization_id,
            'project_id' => $secret->project_id,
            'key' => $secret->key,
            'value' => $this->secrets->maskValue(),
            'created_at' => $secret->created_at,
            'updated_at' => $secret->updated_at,
        ]], 201);
    }

    public function update(Request $request, Organization $organization, Secret $secret): JsonResponse
    {
        $validated = $request->validate([
            'value' => ['required', 'string'],
        ]);

        $secret = $this->secrets->update($secret, $validated['value'], $request->user());

        return response()->json(['data' => [
            'id' => $secret->id,
            'key' => $secret->key,
            'value' => $this->secrets->maskValue(),
        ]]);
    }

    public function destroy(Request $request, Organization $organization, Secret $secret): JsonResponse
    {
        $this->secrets->delete($secret, $request->user());

        return response()->json(null, 204);
    }

    public function reveal(Request $request, Organization $organization, Secret $secret): JsonResponse
    {
        $plaintext = $this->secrets->reveal($secret, $request->user());

        return response()->json(['data' => [
            'id' => $secret->id,
            'key' => $secret->key,
            'value' => $plaintext,
        ]]);
    }
}
