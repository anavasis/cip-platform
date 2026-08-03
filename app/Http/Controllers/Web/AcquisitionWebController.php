<?php

namespace App\Http\Controllers\Web;

use App\Application\Services\JobEngineService;
use App\Domain\Shared\Enums\PlatformJobStatus;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\PlatformJob;
use App\Modules\Acquisition\Domain\Sources\SourceRepositoryInterface;
use App\Modules\Acquisition\Infrastructure\Jobs\AcquireSourceJob;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\AcquisitionRun;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\AcquisitionRunItem;
use App\Support\OperatorContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcquisitionWebController extends Controller
{
    public function __construct(
        private readonly JobEngineService $jobs,
        private readonly SourceRepositoryInterface $sources,
    ) {}

    public function index(Request $request): View
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $query = AcquisitionRun::query()
            ->where('organization_id', $org->id)
            ->where('project_id', $project->id)
            ->orderByDesc('created_at');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return view('app.acquisition.index', [
            'runs' => $query->paginate(25)->withQueryString(),
            'pendingJobs' => PlatformJob::query()
                ->where('organization_id', $org->id)
                ->where('project_id', $project->id)
                ->where('job_type', 'like', 'acquisition%')
                ->whereIn('status', [PlatformJobStatus::Pending, PlatformJobStatus::Running])
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function show(AcquisitionRun $run): View
    {
        $this->assertTenantRun($run);
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $items = AcquisitionRunItem::query()
            ->where('organization_id', $org->id)
            ->where('project_id', $project->id)
            ->where('acquisition_run_id', $run->id)
            ->orderBy('created_at')
            ->get();

        return view('app.acquisition.show', [
            'run' => $run,
            'items' => $items,
        ]);
    }

    public function runDue(): RedirectResponse
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $due = $this->sources->findDue($org->id, $project->id);
        $queued = 0;
        foreach ($due as $source) {
            $sourceId = is_array($source) ? (string) ($source['id'] ?? '') : (string) $source->id;
            if ($sourceId === '') {
                continue;
            }
            $platformJob = $this->jobs->create('acquisition.acquire_source', $org->id, $project->id, [
                'organization_id' => $org->id,
                'project_id' => $project->id,
                'source_id' => $sourceId,
                'trigger' => 'operator_run_due',
                'require_due' => true,
            ]);
            AcquireSourceJob::dispatch($platformJob->id);
            $queued++;
        }

        return back()->with('status', "Queued {$queued} due source run(s).");
    }

    public function retry(AcquisitionRun $run): RedirectResponse
    {
        $this->assertTenantRun($run);
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $item = AcquisitionRunItem::query()
            ->where('acquisition_run_id', $run->id)
            ->where('organization_id', $org->id)
            ->where('project_id', $project->id)
            ->whereNotNull('source_id')
            ->first();
        if ($item === null) {
            return back()->withErrors(['form' => 'Run has no source to retry.']);
        }
        $platformJob = $this->jobs->create('acquisition.acquire_source', $org->id, $project->id, [
            'organization_id' => $org->id,
            'project_id' => $project->id,
            'source_id' => $item->source_id,
            'retry_of' => $run->id,
            'trigger' => 'operator_retry',
        ]);
        AcquireSourceJob::dispatch($platformJob->id);

        return back()->with('status', 'Retry queued.');
    }

    public function cancelPending(PlatformJob $platformJob): RedirectResponse
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        abort_unless(
            $platformJob->organization_id === $org->id
            && $platformJob->project_id === $project->id
            && $platformJob->status === PlatformJobStatus::Pending,
            404
        );
        $platformJob->update([
            'status' => PlatformJobStatus::Failed,
            'error' => 'cancelled_by_operator',
            'completed_at' => now(),
        ]);

        return back()->with('status', 'Pending job cancelled.');
    }

    private function assertTenantRun(AcquisitionRun $run): void
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        abort_unless($run->organization_id === $org->id && $run->project_id === $project->id, 404);
    }
}
