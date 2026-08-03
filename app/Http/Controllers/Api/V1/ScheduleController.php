<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\SchedulerService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use App\Infrastructure\Persistence\Models\ScheduleDefinition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly SchedulerService $scheduler,
    ) {}

    public function index(Organization $organization, ?Project $project = null): JsonResponse
    {
        $query = ScheduleDefinition::query()
            ->where('organization_id', $organization->id);

        if ($project) {
            $query->where('project_id', $project->id);
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function store(Request $request, Organization $organization, ?Project $project = null): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cron_expression' => ['required', 'string', 'max:255'],
            'job_type' => ['required', 'string', 'max:255'],
            'payload' => ['sometimes', 'nullable', 'array'],
            'project_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $schedule = $this->scheduler->create(
            $organization->id,
            $validated['name'],
            $validated['cron_expression'],
            $validated['job_type'],
            $project?->id ?? ($validated['project_id'] ?? null),
            $validated['payload'] ?? null,
        );

        return response()->json(['data' => $schedule], 201);
    }

    public function show(Organization $organization, ScheduleDefinition $schedule): JsonResponse
    {
        return response()->json(['data' => $schedule]);
    }

    public function update(Request $request, Organization $organization, ScheduleDefinition $schedule): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'cron_expression' => ['sometimes', 'string', 'max:255'],
            'job_type' => ['sometimes', 'string', 'max:255'],
            'payload' => ['sometimes', 'nullable', 'array'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $schedule->update($validated);

        return response()->json(['data' => $schedule->fresh()]);
    }

    public function destroy(Organization $organization, ScheduleDefinition $schedule): JsonResponse
    {
        $schedule->delete();

        return response()->json(null, 204);
    }

    public function runDue(): JsonResponse
    {
        $count = $this->scheduler->runDue();

        return response()->json(['data' => ['dispatched' => $count]]);
    }
}
