<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\MonitoringService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitoringController extends Controller
{
    public function __construct(
        private readonly MonitoringService $monitoring,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->monitoring->list($request->query('name')),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'value' => ['required', 'numeric'],
            'tags' => ['sometimes', 'nullable', 'array'],
        ]);

        $metric = $this->monitoring->record(
            $validated['name'],
            (float) $validated['value'],
            $validated['tags'] ?? null,
        );

        return response()->json(['data' => $metric], 201);
    }
}
