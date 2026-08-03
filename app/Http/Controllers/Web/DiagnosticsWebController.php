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
use Illuminate\Support\Collection;
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
            'recentFailedEvents' => $this->tenantFailedEvents((string) $org->id, (string) $project->id),
        ]);
    }

    /**
     * @return Collection<int, array{event_type: string, occurred_at: mixed, error_code: string|null}>
     */
    private function tenantFailedEvents(string $organizationId, string $projectId): Collection
    {
        return StoredEvent::query()
            ->where('event_type', 'like', '%failed%')
            ->where('payload->organization_id', $organizationId)
            ->where('payload->project_id', $projectId)
            ->orderByDesc('occurred_at')
            ->limit(50)
            ->get()
            ->filter(function (StoredEvent $event) use ($organizationId, $projectId): bool {
                $payload = is_array($event->payload) ? $event->payload : [];
                $eventOrg = (string) ($payload['organization_id'] ?? '');
                $eventProject = (string) ($payload['project_id'] ?? '');

                // Events without verifiable tenant context are never displayed.
                return $eventOrg !== ''
                    && $eventProject !== ''
                    && $eventOrg === $organizationId
                    && $eventProject === $projectId;
            })
            ->take(25)
            ->values()
            ->map(static function (StoredEvent $event): array {
                $payload = is_array($event->payload) ? $event->payload : [];

                // Render only safe metadata — never prompt/body/secret/raw provider material.
                return [
                    'event_type' => (string) $event->event_type,
                    'occurred_at' => $event->occurred_at,
                    'error_code' => isset($payload['error_code']) && is_string($payload['error_code'])
                        ? $payload['error_code']
                        : null,
                ];
            });
    }
}
