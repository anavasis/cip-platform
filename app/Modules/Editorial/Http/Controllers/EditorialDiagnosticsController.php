<?php

namespace App\Modules\Editorial\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Editorial\Application\EditorialDiagnostics;
use Illuminate\Http\JsonResponse;

class EditorialDiagnosticsController extends Controller
{
    public function __construct(private readonly EditorialDiagnostics $diagnostics) {}

    public function show(Organization $organization, Project $project): JsonResponse
    {
        return response()->json([
            'data' => $this->diagnostics->snapshot($organization->id, $project->id),
        ]);
    }
}
