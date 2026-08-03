<?php

namespace App\Http\Controllers\Web;

use App\Application\Services\DiagnosticsService;
use App\Application\Services\EventBusService;
use App\Domain\Shared\Enums\PlatformJobStatus;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\Organization;
use App\Infrastructure\Persistence\Models\PlatformJob;
use App\Infrastructure\Persistence\Models\Project;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Editorial\Infrastructure\Persistence\Models\GenerationResultModel;
use App\Support\OperatorContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DiagnosticsService $diagnostics,
        private readonly EventBusService $events,
    ) {}

    public function __invoke(Request $request): View
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $orgId = $org->id;
        $projectId = $project->id;

        $sources = Source::query()->where('organization_id', $orgId)->where('project_id', $projectId);
        $healthy = (clone $sources)->where('last_check_status', 'ok')->count();
        $failedSources = (clone $sources)
            ->whereNotNull('last_check_status')
            ->where('last_check_status', '!=', 'ok')
            ->count();

        $results = GenerationResultModel::query()->where('organization_id', $orgId)->where('project_id', $projectId);
        $completed = (clone $results)->where('status', 'success')->count();
        $failedGen = (clone $results)->where('status', 'error')->count();
        $pendingJobs = PlatformJob::query()
            ->where('organization_id', $orgId)
            ->where('project_id', $projectId)
            ->whereIn('status', [PlatformJobStatus::Pending->value, PlatformJobStatus::Running->value])
            ->where('job_type', 'like', 'editorial%')
            ->count();

        $queueBase = PlatformJob::query()->where('organization_id', $orgId)->where('project_id', $projectId);

        return view('app.dashboard.index', [
            'stats' => [
                'organizations' => Organization::query()->count(),
                'projects' => Project::query()->where('organization_id', $orgId)->count(),
                'sources' => (clone $sources)->count(),
                'healthy_sources' => $healthy,
                'failed_sources' => $failedSources,
                'announcements' => Announcement::query()->where('organization_id', $orgId)->where('project_id', $projectId)->count(),
                'pending_generations' => $pendingJobs,
                'completed_generations' => $completed,
                'failed_generations' => $failedGen,
            ],
            'recentEvents' => collect($this->events->recent(50))
                ->filter(function ($event) use ($orgId, $projectId) {
                    $payload = is_array($event->payload ?? null) ? $event->payload : [];
                    $eventOrg = (string) ($payload['organization_id'] ?? '');
                    $eventProject = (string) ($payload['project_id'] ?? '');
                    if ($eventOrg === '' && $eventProject === '') {
                        return true;
                    }

                    return $eventOrg === (string) $orgId
                        && ($eventProject === '' || $eventProject === (string) $projectId);
                })
                ->take(15)
                ->values(),
            'health' => $this->diagnostics->health(),
            'queue' => [
                'pending' => (clone $queueBase)->where('status', PlatformJobStatus::Pending->value)->count(),
                'running' => (clone $queueBase)->where('status', PlatformJobStatus::Running->value)->count(),
                'failed' => (clone $queueBase)->where('status', PlatformJobStatus::Failed->value)->count(),
            ],
            'notifications' => PlatformJob::query()
                ->where('organization_id', $orgId)
                ->where('project_id', $projectId)
                ->where('status', PlatformJobStatus::Failed)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),
        ]);
    }
}
