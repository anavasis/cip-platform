<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\JobEngineService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Jobs\PingJob;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function __construct(
        private readonly JobEngineService $jobs,
    ) {}

    public function index(Request $request, ?Organization $organization = null): JsonResponse
    {
        $filters = array_filter([
            'organization_id' => $organization?->id ?? $request->query('organization_id'),
            'project_id' => $request->query('project_id'),
            'status' => $request->query('status'),
        ]);

        return response()->json(['data' => $this->jobs->list($filters)]);
    }

    public function dispatchPing(Request $request, ?Organization $organization = null, ?Project $project = null): JsonResponse
    {
        $job = $this->jobs->create(
            'ping',
            $organization?->id,
            $project?->id,
            ['source' => 'api'],
        );

        PingJob::dispatch($job->id);

        return response()->json(['data' => $job], 202);
    }
}
