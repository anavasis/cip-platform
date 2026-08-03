<?php

namespace App\Http\Controllers\Web;

use App\Application\Services\DiagnosticsService;
use App\Domain\Shared\Enums\PlatformJobStatus;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\PlatformJob;
use App\Infrastructure\Persistence\Models\StoredEvent;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\AcquisitionRun;
use App\Modules\Editorial\Application\EditorialDiagnostics;
use App\Modules\Editorial\Infrastructure\Persistence\Models\GenerationResultModel;
use App\Support\OperatorContext;
use Illuminate\View\View;

class DiagnosticsWebController extends Controller
{
    public function __construct(
        private readonly DiagnosticsService $diagnostics,
        private readonly EditorialDiagnostics $editorialDiagnostics,
    ) {}

    public function __invoke(): View
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();

        return view('app.diagnostics.index', [
            'health' => $this->diagnostics->health(),
            'editorial' => $this->editorialDiagnostics->snapshot($org->id, $project->id),
            'generationFailures' => GenerationResultModel::query()
                ->where('organization_id', $org->id)
                ->where('project_id', $project->id)
                ->where('status', 'error')
                ->orderByDesc('created_at')
                ->limit(25)
                ->get(),
            'acquisitionFailures' => AcquisitionRun::query()
                ->where('organization_id', $org->id)
                ->where('project_id', $project->id)
                ->whereIn('status', ['failed', 'error'])
                ->orderByDesc('created_at')
                ->limit(25)
                ->get(),
            'queueFailures' => PlatformJob::query()
                ->where('organization_id', $org->id)
                ->where('project_id', $project->id)
                ->where('status', PlatformJobStatus::Failed)
                ->orderByDesc('created_at')
                ->limit(25)
                ->get(),
            'recentFailedEvents' => StoredEvent::query()
                ->where('event_type', 'like', '%failed%')
                ->orderByDesc('occurred_at')
                ->limit(25)
                ->get(),
        ]);
    }
}
