<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\ProjectService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectMembershipController extends Controller
{
    public function __construct(
        private readonly ProjectService $projects,
    ) {}

    public function index(Organization $organization, Project $project): JsonResponse
    {
        return response()->json(['data' => $this->projects->listMembers($project)]);
    }

    public function store(Request $request, Organization $organization, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'role' => ['required', 'string', 'in:admin,editor,viewer'],
        ]);

        $member = User::findOrFail($validated['user_id']);
        $membership = $this->projects->addMember($project, $member, $validated['role'], $request->user());

        return response()->json(['data' => $membership], 201);
    }
}
