<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\OrganizationService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizations,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $orgs = $this->organizations->listForUser($request->user());

        return response()->json(['data' => $orgs]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'unique:organizations,slug'],
        ]);

        $org = $this->organizations->create($request->user(), $validated['name'], $validated['slug'] ?? null);

        return response()->json(['data' => $org], 201);
    }

    public function show(Organization $organization): JsonResponse
    {
        return response()->json(['data' => $organization]);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:255', 'unique:organizations,slug,'.$organization->id],
        ]);

        $org = $this->organizations->update($organization, $validated, $request->user());

        return response()->json(['data' => $org]);
    }

    public function destroy(Request $request, Organization $organization): JsonResponse
    {
        $this->organizations->delete($organization, $request->user());

        return response()->json(null, 204);
    }
}
