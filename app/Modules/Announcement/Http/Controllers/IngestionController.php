<?php

namespace App\Modules\Announcement\Http\Controllers;

use App\Application\Services\JobEngineService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Infrastructure\Jobs\IngestSourceJob;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use Illuminate\Http\JsonResponse;

class IngestionController extends Controller
{
    public function __construct(private readonly JobEngineService $jobs) {}

    public function store(
        Organization $organization,
        Project $project,
        Source $source,
    ): JsonResponse {
        $job = $this->jobs->create(
            'announcement.ingest_source',
            $organization->id,
            $project->id,
            [
                'organization_id' => $organization->id,
                'project_id' => $project->id,
                'source_id' => (string) $source->id,
                'trigger' => 'api',
            ],
        );
        IngestSourceJob::dispatch($job->id);

        return response()->json(['data' => $job], 202);
    }
}
