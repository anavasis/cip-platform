<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\OrganizationService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationMembershipController extends Controller
{
    public function __construct(
        private readonly OrganizationService $organizations,
    ) {}

    public function index(Organization $organization): JsonResponse
    {
        return response()->json(['data' => $this->organizations->listMembers($organization)]);
    }

    public function store(Request $request, Organization $organization): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'role' => ['required', 'string', 'in:owner,admin,member'],
        ]);

        $member = User::findOrFail($validated['user_id']);
        $membership = $this->organizations->addMember($organization, $member, $validated['role'], $request->user());

        return response()->json(['data' => $membership], 201);
    }
}
