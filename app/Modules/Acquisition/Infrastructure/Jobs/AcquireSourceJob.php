<?php

namespace App\Modules\Acquisition\Infrastructure\Jobs;

use App\Application\Services\EventBusService;
use App\Application\Services\JobEngineService;
use App\Infrastructure\Persistence\Models\PlatformJob;
use App\Modules\Acquisition\Application\CapabilityGate;
use App\Modules\Acquisition\Application\ProductionAcquisitionOrchestrator;
use App\Modules\Acquisition\Domain\Events\AcquisitionRunCompleted;
use App\Modules\Acquisition\Domain\Events\AcquisitionRunFailed;
use App\Modules\Acquisition\Domain\Events\AcquisitionRunStarted;
use App\Modules\Acquisition\Domain\Sources\SourceRepositoryInterface;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use App\Modules\Acquisition\Infrastructure\Persistence\Repositories\EloquentAcquisitionRunRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AcquireSourceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $platformJobId) {}

    public function handle(
        JobEngineService $jobEngine,
        EventBusService $eventBus,
        ProductionAcquisitionOrchestrator $orchestrator,
        EloquentAcquisitionRunRepository $runs,
        SourceRepositoryInterface $sources,
        CapabilityGate $capabilityGate,
    ): void {
        $job = PlatformJob::findOrFail($this->platformJobId);
        $job->forceFill(['error' => null, 'completed_at' => null])->save();
        $job = $jobEngine->markRunning($job);
        $payload = is_array($job->payload) ? $job->payload : [];
        $organizationId = trim((string) ($job->organization_id ?? ''));
        $projectId = trim((string) ($job->project_id ?? ''));
        $sourceId = trim((string) ($payload['source_id'] ?? ''));
        $runId = (string) Str::uuid();
        $storedRunId = false;
        $terminalized = false;
        $failureEventEmitted = false;
        $itemStoreAttempted = false;
        $sourceExists = false;
        $sourcesRequested = $sourceId === '' ? 0 : 1;
        $sourcesSucceeded = 0;
        $sourcesFailed = 0;
        $durationMs = 0.0;
        $errorCode = 'acquisition_job_failed';
        $startedAt = microtime(true);
        $sourceLock = null;
        $lockAcquired = false;

        try {
            if ($organizationId === '' || $projectId === '') {
                throw new RuntimeException('invalid_payload');
            }

            $storedRunId = $runs->createRun([
                'organization_id' => $organizationId,
                'project_id' => $projectId,
                'run_id' => $runId,
                'status' => 'running',
                'sources_requested' => $sourcesRequested,
                'sources_succeeded' => 0,
                'sources_failed' => 0,
                'meta' => [
                    'platform_job_id' => $job->id,
                    'attempt' => $this->attempts(),
                ],
            ]);

            if ($storedRunId === false) {
                throw new RuntimeException('run_persist_failed');
            }

            $eventBus->dispatch(new AcquisitionRunStarted(
                $organizationId,
                $projectId,
                $runId,
                $sourcesRequested,
            ));

            if ($sourceId === ''
                || trim((string) ($payload['organization_id'] ?? '')) !== $organizationId
                || trim((string) ($payload['project_id'] ?? '')) !== $projectId) {
                throw new RuntimeException('invalid_payload');
            }

            $source = Source::query()
                ->where('organization_id', $organizationId)
                ->where('project_id', $projectId)
                ->whereKey($sourceId)
                ->first();
            $sourceExists = $source !== null;

            if ($source === null) {
                throw new RuntimeException('not_found');
            }

            if (! $source->enabled) {
                throw new RuntimeException('source_disabled');
            }

            if ($source->manual_only) {
                throw new RuntimeException('source_manual_only');
            }

            if (! $capabilityGate->isEnabledFor(
                CapabilityGate::ACQUISITION,
                $organizationId,
                $projectId,
            ) || ! $capabilityGate->isEnabledFor(
                CapabilityGate::SOURCE_REGISTRY,
                $organizationId,
                $projectId,
            )) {
                throw new RuntimeException('capability_disabled');
            }

            if (($payload['require_due'] ?? false) === true && ! $source->isDueForAcquisition()) {
                throw new RuntimeException('source_not_due');
            }

            $sourceLock = Cache::lock(
                "acquisition:project:{$projectId}:source:{$sourceId}",
                900,
            );
            $lockAcquired = $sourceLock->get();

            if (! $lockAcquired) {
                throw new RuntimeException('source_locked');
            }

            $result = $orchestrator->run(
                $organizationId,
                $projectId,
                [$sourceId],
                $job->id,
                $runId,
            );
            $sourcesRequested = $result->sourcesRequested();
            $sourcesSucceeded = $result->sourcesSucceeded();
            $sourcesFailed = $result->sourcesFailed();
            $durationMs = $result->durationMs();

            foreach ($result->results() as $item) {
                $itemStoreAttempted = true;
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
                : $this->resultErrorCode($result->errorCode(), $result->results());

            if (! $result->success()) {
                throw new RuntimeException($errorCode);
            }

            $updated = $runs->updateRun($runId, [
                'organization_id' => $organizationId,
                'project_id' => $projectId,
                'status' => 'completed',
                'error_code' => null,
                'sources_requested' => $sourcesRequested,
                'sources_succeeded' => $sourcesSucceeded,
                'sources_failed' => $sourcesFailed,
                'duration_ms' => $durationMs,
            ]);

            if (! $updated) {
                throw new RuntimeException('run_update_failed');
            }

            $sources->update($sourceId, [
                'organization_id' => $organizationId,
                'project_id' => $projectId,
                'last_acquired_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $eventBus->dispatch(new AcquisitionRunCompleted(
                $organizationId,
                $projectId,
                $runId,
                $sourcesRequested,
                $sourcesSucceeded,
                $sourcesFailed,
                $durationMs,
            ));
            $jobEngine->markCompleted($job, $result->toArray());
            $terminalized = true;
        } catch (Throwable $throwable) {
            $errorCode = $this->exceptionErrorCode($throwable);
            $sourcesFailed = max($sourcesFailed, $sourcesRequested);
            $durationMs = max($durationMs, (microtime(true) - $startedAt) * 1000);

            if ($storedRunId !== false && ! $itemStoreAttempted && $sourceId !== '') {
                $itemStoreAttempted = true;
                $runs->createItem([
                    'acquisition_run_id' => $storedRunId,
                    'organization_id' => $organizationId,
                    'project_id' => $projectId,
                    'source_id' => $sourceExists ? $sourceId : null,
                    'success' => false,
                    'error_code' => $errorCode,
                    'result_meta' => [
                        'source_id' => $sourceId,
                        'success' => false,
                        'error_code' => $errorCode,
                    ],
                ]);
            }

            if ($storedRunId !== false && ! $terminalized) {
                $terminalized = $runs->updateRun($runId, [
                    'organization_id' => $organizationId,
                    'project_id' => $projectId,
                    'status' => 'failed',
                    'error_code' => $errorCode,
                    'sources_requested' => $sourcesRequested,
                    'sources_succeeded' => $sourcesSucceeded,
                    'sources_failed' => $sourcesFailed,
                    'duration_ms' => $durationMs,
                ]);

                if (! $terminalized) {
                    $errorCode = 'run_update_failed';
                }
            }

            if ($storedRunId !== false && ! $failureEventEmitted) {
                $failureEventEmitted = true;

                try {
                    $eventBus->dispatch(new AcquisitionRunFailed(
                        $organizationId,
                        $projectId,
                        $runId,
                        $errorCode,
                        $sourcesRequested,
                        $durationMs,
                    ));
                } catch (Throwable) {
                    // Run and platform job rows remain the durable signals.
                }
            }

            $jobEngine->markFailed($job, $errorCode);

            if ($this->isRetryable($errorCode, $throwable)) {
                throw new RuntimeException($errorCode, 0, $throwable);
            }
        } finally {
            if ($storedRunId !== false && ! $terminalized) {
                $runs->updateRun($runId, [
                    'organization_id' => $organizationId,
                    'project_id' => $projectId,
                    'status' => 'failed',
                    'error_code' => $errorCode,
                    'sources_requested' => $sourcesRequested,
                    'sources_succeeded' => $sourcesSucceeded,
                    'sources_failed' => max($sourcesFailed, $sourcesRequested),
                    'duration_ms' => max($durationMs, (microtime(true) - $startedAt) * 1000),
                ]);
            }

            if ($lockAcquired) {
                $sourceLock?->release();
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function resultErrorCode(string $runErrorCode, array $items): string
    {
        if ($runErrorCode !== '') {
            return $runErrorCode;
        }

        foreach ($items as $item) {
            $itemError = trim((string) ($item['error_code'] ?? ''));

            if ($itemError !== '') {
                return $itemError;
            }
        }

        return 'acquisition_failed';
    }

    private function exceptionErrorCode(Throwable $throwable): string
    {
        $message = trim($throwable->getMessage());

        return preg_match('/^[a-z][a-z0-9_]{1,63}$/', $message) === 1
            ? $message
            : 'acquisition_job_failed';
    }

    private function isRetryable(string $errorCode, Throwable $throwable): bool
    {
        if (in_array($errorCode, [
            'capability_disabled',
            'invalid_payload',
            'not_found',
            'source_disabled',
            'source_manual_only',
            'source_not_due',
        ], true)) {
            return false;
        }

        if (in_array($errorCode, [
            'transport_error',
            'http_error',
            'source_locked',
            'run_persist_failed',
            'run_item_persist_failed',
            'run_update_failed',
            'acquisition_job_failed',
        ], true)) {
            return true;
        }

        return ! $throwable instanceof RuntimeException;
    }
}
