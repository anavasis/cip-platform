<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\FeatureFlagService;
use App\Domain\Shared\Enums\FeatureFlagScope;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeatureFlagController extends Controller
{
    public function __construct(
        private readonly FeatureFlagService $flags,
    ) {}

    public function index(Request $request, ?Organization $organization = null, ?Project $project = null): JsonResponse
    {
        return response()->json([
            'data' => $this->flags->list($organization?->id, $project?->id),
        ]);
    }

    public function upsert(Request $request, ?Organization $organization = null, ?Project $project = null): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'enabled' => ['required', 'boolean'],
            'value' => ['sometimes', 'nullable', 'array'],
            'scope' => ['required', 'string', 'in:global,organization,project'],
            'organization_id' => ['sometimes', 'nullable', 'uuid'],
            'project_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $scope = FeatureFlagScope::from($validated['scope']);
        $orgId = $organization?->id ?? ($validated['organization_id'] ?? null);
        $projId = $project?->id ?? ($validated['project_id'] ?? null);

        $flag = $this->flags->upsert(
            $validated['key'],
            $validated['enabled'],
            $scope,
            $validated['value'] ?? null,
            $orgId,
            $projId,
            $request->user(),
        );

        return response()->json(['data' => $flag], 201);
    }
}
