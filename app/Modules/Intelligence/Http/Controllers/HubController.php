<?php

namespace App\Modules\Intelligence\Http\Controllers;

use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Intelligence\Application\HubPayloadBuilder;
use Illuminate\Http\JsonResponse;

final class HubController
{
    public function __construct(
        private readonly HubPayloadBuilder $hubPayloadBuilder,
    ) {}

    public function show(Organization $organization, Project $project): JsonResponse
    {
        $payload = $this->hubPayloadBuilder->build($organization->id, $project->id);

        return response()->json($payload);
    }
}
