<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\AuditService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request, ?Organization $organization = null): JsonResponse
    {
        $filters = array_filter([
            'organization_id' => $organization?->id ?? $request->query('organization_id'),
            'project_id' => $request->query('project_id'),
            'action' => $request->query('action'),
            'user_id' => $request->query('user_id'),
        ]);

        return response()->json(['data' => $this->audit->list($filters)]);
    }
}
