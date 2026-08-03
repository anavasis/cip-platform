<?php

namespace App\Modules\Editorial\Infrastructure\Jobs;

use App\Application\Services\EventBusService;
use App\Application\Services\JobEngineService;
use App\Infrastructure\Persistence\Models\PlatformJob;
use App\Modules\Editorial\Application\CapabilityGate;
use App\Modules\Editorial\Application\GenerateArticlePreviewService;
use App\Modules\Editorial\Domain\Events\GenerationFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class GenerateArticlePreviewJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [5, 15, 30];

    public function __construct(public readonly string $platformJobId)
    {
        $this->onQueue('editorial');
    }

    public function handle(
        JobEngineService $jobEngine,
        GenerateArticlePreviewService $service,
        CapabilityGate $capabilityGate,
        EventBusService $eventBus,
    ): void {
        $job = PlatformJob::findOrFail($this->platformJobId);
        $job->forceFill(['error' => null, 'completed_at' => null])->save();
        $job = $jobEngine->markRunning($job);
        $payload = is_array($job->payload) ? $job->payload : [];

        $organizationId = trim((string) ($job->organization_id ?? ''));
        $projectId = trim((string) ($job->project_id ?? ''));
        $announcementId = trim((string) ($payload['announcement_id'] ?? ''));
        $actorId = isset($payload['actor_id']) ? trim((string) $payload['actor_id']) : null;
        $correlationId = trim((string) ($payload['correlation_id'] ?? $job->id));
        $regenerate = ($payload['regenerate'] ?? false) === true;
        $failureEventEmitted = false;
        $lock = null;
        $lockAcquired = false;

        try {
            if ($organizationId === '' || $projectId === '' || $announcementId === '') {
                throw new RuntimeException('invalid_payload');
            }

            if (trim((string) ($payload['organization_id'] ?? '')) !== $organizationId
                || trim((string) ($payload['project_id'] ?? '')) !== $projectId) {
                throw new RuntimeException('invalid_payload');
            }

            if (! $capabilityGate->generationAllowed($organizationId, $projectId)) {
                throw new RuntimeException('capability_disabled');
            }

            $lock = Cache::lock(
                "editorial:project:{$projectId}:announcement:{$announcementId}",
                60,
            );
            $lockAcquired = $lock->get();
            if (! $lockAcquired) {
                throw new RuntimeException('announcement_locked');
            }

            $result = $service->executeLocked(
                $organizationId,
                $projectId,
                $announcementId,
                $actorId !== '' ? $actorId : null,
                $correlationId,
                $regenerate,
            );

            if (($result['ok'] ?? false) !== true) {
                throw new RuntimeException((string) ($result['error_code'] ?? $result['error'] ?? 'generation_failed'));
            }

            $jobEngine->markCompleted($job, [
                'request_id' => $result['request_id'] ?? null,
                'result_id' => $result['result_id'] ?? null,
                'preview_id' => $result['preview_id'] ?? null,
                'correlation_id' => $correlationId,
                'reused' => $result['reused'] ?? false,
            ]);
        } catch (Throwable $e) {
            $errorCode = $this->exceptionErrorCode($e);

            if (! $failureEventEmitted && $organizationId !== '' && $projectId !== '') {
                try {
                    $eventBus->dispatch(new GenerationFailed(
                        organizationId: $organizationId,
                        projectId: $projectId,
                        announcementId: $announcementId !== '' ? $announcementId : null,
                        errorCode: $errorCode,
                        actorId: $actorId !== '' ? $actorId : null,
                        correlationId: $correlationId,
                    ));
                    $failureEventEmitted = true;
                } catch (Throwable) {
                    // ignore
                }
            }

            if ($this->attempts() >= $this->tries || ! $this->isRetryable($errorCode, $e)) {
                $jobEngine->markFailed($job, $errorCode);
            }

            throw $e;
        } finally {
            if ($lockAcquired && $lock !== null) {
                try {
                    $lock->release();
                } catch (Throwable) {
                }
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        try {
            $job = PlatformJob::find($this->platformJobId);
            if ($job === null) {
                return;
            }
            if ($job->completed_at !== null) {
                return;
            }
            $error = $exception ? $this->exceptionErrorCode($exception) : 'editorial_job_failed';
            app(JobEngineService::class)->markFailed($job, $error);
        } catch (Throwable) {
        }
    }

    private function exceptionErrorCode(Throwable $throwable): string
    {
        $message = trim($throwable->getMessage());

        return preg_match('/^[a-z][a-z0-9_]{1,63}$/', $message) === 1
            ? $message
            : 'editorial_job_failed';
    }

    private function isRetryable(string $errorCode, Throwable $throwable): bool
    {
        if (in_array($errorCode, [
            'capability_disabled',
            'invalid_payload',
            'announcement_not_found',
        ], true)) {
            return false;
        }

        if (in_array($errorCode, [
            'announcement_locked',
            'editorial_job_failed',
        ], true)) {
            return true;
        }

        return ! $throwable instanceof RuntimeException;
    }
}
