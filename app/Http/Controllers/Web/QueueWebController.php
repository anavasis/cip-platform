<?php

namespace App\Http\Controllers\Web;

use App\Application\Services\JobEngineService;
use App\Domain\Shared\Enums\PlatformJobStatus;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Models\PlatformJob;
use App\Support\OperatorContext;
use App\Support\PlatformJobDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QueueWebController extends Controller
{
    public function __construct(private readonly JobEngineService $jobs) {}

    public function index(Request $request): View
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        $status = $request->string('status')->toString();
        $query = PlatformJob::query()
            ->where('organization_id', $org->id)
            ->where('project_id', $project->id)
            ->orderByDesc('created_at');
        if (in_array($status, ['pending', 'running', 'completed', 'failed'], true)) {
            $query->where('status', $status);
        }

        return view('app.queue.index', [
            'jobs' => $query->paginate(30)->withQueryString(),
            'status' => $status,
            'counts' => [
                'pending' => PlatformJob::query()->where('organization_id', $org->id)->where('project_id', $project->id)->where('status', PlatformJobStatus::Pending)->count(),
                'running' => PlatformJob::query()->where('organization_id', $org->id)->where('project_id', $project->id)->where('status', PlatformJobStatus::Running)->count(),
                'completed' => PlatformJob::query()->where('organization_id', $org->id)->where('project_id', $project->id)->where('status', PlatformJobStatus::Completed)->count(),
                'failed' => PlatformJob::query()->where('organization_id', $org->id)->where('project_id', $project->id)->where('status', PlatformJobStatus::Failed)->count(),
            ],
        ]);
    }

    public function show(PlatformJob $platformJob): View
    {
        $this->assertTenant($platformJob);

        return view('app.queue.show', ['job' => $platformJob]);
    }

    public function retry(PlatformJob $platformJob): RedirectResponse
    {
        $this->assertTenant($platformJob);
        $clone = $this->jobs->create(
            (string) $platformJob->job_type,
            $platformJob->organization_id,
            $platformJob->project_id,
            is_array($platformJob->payload) ? $platformJob->payload : [],
        );

        if (! PlatformJobDispatcher::dispatch((string) $clone->job_type, (string) $clone->id)) {
            return redirect()
                ->route('app.queue.show', $clone)
                ->withErrors(['form' => 'Retry record created, but job type is not dispatchable from the operator UI.']);
        }

        return redirect()->route('app.queue.show', $clone)->with('status', 'Retry job queued.');
    }

    public function cancel(PlatformJob $platformJob): RedirectResponse
    {
        $this->assertTenant($platformJob);
        abort_unless($platformJob->status === PlatformJobStatus::Pending, 422);
        $platformJob->update([
            'status' => PlatformJobStatus::Failed,
            'error' => 'cancelled_by_operator',
            'completed_at' => now(),
        ]);

        return back()->with('status', 'Pending job cancelled.');
    }

    private function assertTenant(PlatformJob $platformJob): void
    {
        $org = OperatorContext::organization();
        $project = OperatorContext::project();
        abort_unless(
            $platformJob->organization_id === $org->id && $platformJob->project_id === $project->id,
            404
        );
    }
}
