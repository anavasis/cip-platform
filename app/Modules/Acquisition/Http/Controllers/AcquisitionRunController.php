<?php

namespace App\Modules\Acquisition\Http\Controllers;

use App\Application\Services\JobEngineService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Infrastructure\Jobs\AcquireSourceJob;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\AcquisitionRun;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\AcquisitionRunItem;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AcquisitionRunController extends Controller
{
    public function __construct(private readonly JobEngineService $jobs) {}

    public function index(
        Request $request,
        Organization $organization,
        Project $project,
    ): JsonResponse {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'max:64'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $query = AcquisitionRun::query()
            ->where('organization_id', $organization->id)
            ->where('project_id', $project->id)
            ->orderByDesc('created_at');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        return response()->json([
            'data' => $query->paginate((int) ($validated['per_page'] ?? 25)),
        ]);
    }

    public function store(
        Request $request,
        Organization $organization,
        Project $project,
        ?Source $source = null,
    ): JsonResponse {
        $validated = $request->validate([
            'source_ids' => ['sometimes', 'array', 'min:1', 'max:100'],
            'source_ids.*' => ['required', 'uuid', 'distinct'],
        ]);
        $sourceIds = $source !== null
            ? [(string) $source->id]
            : array_values($validated['source_ids'] ?? []);

        if ($sourceIds === []) {
            throw ValidationException::withMessages([
                'source_ids' => ['At least one source is required.'],
            ]);
        }

        $validSourceIds = Source::query()
            ->where('organization_id', $organization->id)
            ->where('project_id', $project->id)
            ->whereIn('id', $sourceIds)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        if (count($validSourceIds) !== count($sourceIds)) {
            throw ValidationException::withMessages([
                'source_ids' => ['One or more sources do not belong to this project.'],
            ]);
        }

        $platformJobs = [];

        foreach ($sourceIds as $sourceId) {
            $job = $this->jobs->create(
                'acquisition.acquire_source',
                $organization->id,
                $project->id,
                [
                    'organization_id' => $organization->id,
                    'project_id' => $project->id,
                    'source_id' => $sourceId,
                    'trigger' => 'api',
                ],
            );
            AcquireSourceJob::dispatch($job->id);
            $platformJobs[] = $job;
        }

        return response()->json(['data' => $platformJobs], 202);
    }

    public function show(
        Organization $organization,
        Project $project,
        AcquisitionRun $acquisitionRun,
    ): JsonResponse {
        $data = $acquisitionRun->toArray();
        $data['items'] = AcquisitionRunItem::query()
            ->where('organization_id', $organization->id)
            ->where('project_id', $project->id)
            ->where('acquisition_run_id', $acquisitionRun->id)
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => $data]);
    }
}
