<?php

namespace App\Modules\Announcement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Announcement\Domain\AnnouncementRepositoryInterface;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementRepositoryInterface $announcements,
    ) {}

    public function index(
        Request $request,
        Organization $organization,
        Project $project,
    ): JsonResponse {
        $criteria = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'source_id' => ['sometimes', 'uuid'],
            'status' => ['sometimes', 'string', 'in:NEW,UPDATED'],
        ]);

        return response()->json([
            'data' => $this->announcements->findPage(
                $organization->id,
                $project->id,
                $criteria,
            ),
        ]);
    }

    public function summary(Organization $organization, Project $project): JsonResponse
    {
        return response()->json([
            'data' => $this->announcements->findEditorialSummary(
                $organization->id,
                $project->id,
            ),
        ]);
    }

    public function show(
        Organization $organization,
        Project $project,
        Announcement $announcement,
    ): JsonResponse {
        return response()->json(['data' => $announcement]);
    }
}
