<?php

namespace App\Modules\Acquisition\Infrastructure\Jobs;

use App\Application\Services\EventBusService;
use App\Application\Services\JobEngineService;
use App\Infrastructure\Persistence\Models\PlatformJob;
use App\Modules\Acquisition\Application\ProductionAcquisitionOrchestrator;
use App\Modules\Acquisition\Domain\Events\AcquisitionRunCompleted;
use App\Modules\Acquisition\Domain\Events\AcquisitionRunFailed;
use App\Modules\Acquisition\Domain\Events\AcquisitionRunStarted;
use App\Modules\Acquisition\Domain\Sources\SourceRepositoryInterface;
use App\Modules\Acquisition\Infrastructure\Persistence\Repositories\EloquentAcquisitionRunRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class AcquireSourceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $platformJobId) {}

    public function handle(
        JobEngineService $jobEngine,
        EventBusService $eventBus,
        ProductionAcquisitionOrchestrator $orchestrator,
        EloquentAcquisitionRunRepository $runs,
        SourceRepositoryInterface $sources,
    ): void {
        $job = PlatformJob::findOrFail($this->platformJobId);
        $job = $jobEngine->markRunning($job);
        $payload = is_array($job->payload) ? $job->payload : [];
        $organizationId = trim((string) ($payload['organization_id'] ?? $job->organization_id ?? ''));
        $projectId = trim((string) ($payload['project_id'] ?? $job->project_id ?? ''));
        $sourceId = trim((string) ($payload['source_id'] ?? ''));
        $runId = $job->id;

        try {
            if ($organizationId === '' || $projectId === '' || $sourceId === '') {
                throw new RuntimeException('invalid_payload');
            }

            $result = $orchestrator->run($organizationId, $projectId, [$sourceId]);
            $runId = $result->runId();
            $storedRunId = $runs->createRun([
                'organization_id' => $organizationId,
                'project_id' => $projectId,
                'run_id' => $runId,
                'status' => 'running',
                'sources_requested' => $result->sourcesRequested(),
                'sources_succeeded' => 0,
                'sources_failed' => 0,
                'meta' => ['platform_job_id' => $job->id],
            ]);

            if ($storedRunId === false) {
                throw new RuntimeException('run_persist_failed');
            }

            $eventBus->dispatch(new AcquisitionRunStarted(
                $organizationId,
                $projectId,
                $runId,
                $result->sourcesRequested(),
            ));

            foreach ($result->results() as $item) {
                $storedItemId = $runs->createItem([
                    'acquisition_run_id' => $storedRunId,
                    'organization_id' => $organizationId,
                    'project_id' => $projectId,
                    'source_id' => isset($item['source_id']) ? (string) $item['source_id'] : null,
                    'success' => ($item['success'] ?? false) === true,
                    'error_code' => ($item['error_code'] ?? '') !== '' ? (string) $item['error_code'] : null,
                    'result_meta' => $item,
                ]);

                if ($storedItemId === false) {
                    throw new RuntimeException('run_item_persist_failed');
                }
            }

            $errorCode = $result->success()
                ? ''
                : ($result->errorCode() !== '' ? $result->errorCode() : 'acquisition_failed');
            $updated = $runs->updateRun($runId, [
                'organization_id' => $organizationId,
                'project_id' => $projectId,
                'status' => $result->success() ? 'completed' : 'failed',
                'error_code' => $errorCode !== '' ? $errorCode : null,
                'sources_requested' => $result->sourcesRequested(),
                'sources_succeeded' => $result->sourcesSucceeded(),
                'sources_failed' => $result->sourcesFailed(),
                'duration_ms' => $result->durationMs(),
            ]);

            if (! $updated) {
                throw new RuntimeException('run_update_failed');
            }

            if ($result->success()) {
                $sources->update($sourceId, [
                    'organization_id' => $organizationId,
                    'project_id' => $projectId,
                    'last_acquired_at' => gmdate('Y-m-d H:i:s'),
                ]);
                $eventBus->dispatch(new AcquisitionRunCompleted(
                    $organizationId,
                    $projectId,
                    $runId,
                    $result->sourcesRequested(),
                    $result->sourcesSucceeded(),
                    $result->sourcesFailed(),
                    $result->durationMs(),
                ));
                $jobEngine->markCompleted($job, $result->toArray());

                return;
            }

            $eventBus->dispatch(new AcquisitionRunFailed(
                $organizationId,
                $projectId,
                $runId,
                $errorCode,
                $result->sourcesRequested(),
                $result->durationMs(),
            ));
            $jobEngine->markFailed($job, $errorCode);
        } catch (Throwable $throwable) {
            $errorCode = $throwable->getMessage() !== '' ? $throwable->getMessage() : 'acquisition_job_failed';

            try {
                $eventBus->dispatch(new AcquisitionRunFailed(
                    $organizationId,
                    $projectId,
                    $runId,
                    $errorCode,
                ));
            } catch (Throwable) {
                // The platform job status remains the durable failure signal.
            }

            $jobEngine->markFailed($job, $errorCode);
        }
    }
}
