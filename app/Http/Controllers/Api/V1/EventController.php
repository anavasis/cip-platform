<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Services\EventBusService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private readonly EventBusService $eventBus,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 50);

        return response()->json(['data' => $this->eventBus->recent($limit)]);
    }
}
