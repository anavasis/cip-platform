<?php

namespace App\Modules\Acquisition\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Application\AcquisitionDiagnostics;
use Illuminate\Http\JsonResponse;

class AcquisitionDiagnosticsController extends Controller
{
    public function __construct(private readonly AcquisitionDiagnostics $diagnostics) {}

    public function show(Organization $organization, Project $project): JsonResponse
    {
        return response()->json([
            'data' => $this->diagnostics->status($organization->id, $project->id),
        ]);
    }
}
