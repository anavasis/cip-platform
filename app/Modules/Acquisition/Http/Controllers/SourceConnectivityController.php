<?php

namespace App\Modules\Acquisition\Http\Controllers;

use App\Application\Services\JobEngineService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Infrastructure\Jobs\SourceConnectivityCheckJob;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use Illuminate\Http\JsonResponse;

class SourceConnectivityController extends Controller
{
    public function __construct(private readonly JobEngineService $jobs) {}

    public function store(
        Organization $organization,
        Project $project,
        Source $source,
    ): JsonResponse {
        $job = $this->jobs->create(
            'acquisition.source_connectivity_check',
            $organization->id,
            $project->id,
            [
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'source_id' => (string) $source->id,
                'trigger' => 'api',
            ],
        );
        SourceConnectivityCheckJob::dispatch($job->id);

        return response()->json(['data' => $job], 202);
    }
}
