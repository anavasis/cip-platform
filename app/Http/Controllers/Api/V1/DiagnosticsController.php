<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\DiagnosticsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DiagnosticsController extends Controller
{
    public function __construct(
        private readonly DiagnosticsService $diagnostics,
    ) {}

    public function health(): JsonResponse
    {
        return response()->json(['data' => $this->diagnostics->health()]);
    }
}
