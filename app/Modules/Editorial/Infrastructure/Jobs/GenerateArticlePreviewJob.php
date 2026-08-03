<?php

namespace App\Modules\Editorial\Infrastructure\Jobs;

use App\Application\Services\EventBusService;
use App\Application\Services\JobEngineService;
use App\Infrastructure\Persistence\Models\PlatformJob;
use App\Modules\Editorial\Application\CapabilityGate;
use App\Modules\Editorial\Application\GenerateArticlePreviewService;
use App\Modules\Editorial\Domain\Events\GenerationFailed;
use App\Modules\Editorial\Domain\GenerationResult\EditorialErrorCodes;
use App\Modules\Editorial\Domain\GenerationResult\EditorialGenerationException;
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
        $domainFailureRecorded = false;
        $lock = null;
        $lockAcquired = false;

        try {
            if ($organizationId === '' || $projectId === '' || $announcementId === '') {
                throw EditorialGenerationException::permanent(EditorialErrorCodes::INVALID_PAYLOAD);
            }

            if (trim((string) ($payload['organization_id'] ?? '')) !== $organizationId
                || trim((string) ($payload['project_id'] ?? '')) !== $projectId) {
                throw EditorialGenerationException::permanent(EditorialErrorCodes::INVALID_PAYLOAD);
            }

            if (! $capabilityGate->generationAllowed($organizationId, $projectId)) {
                throw EditorialGenerationException::permanent(EditorialErrorCodes::CAPABILITY_DISABLED);
            }

            $lock = Cache::lock(
                "editorial:project:{$projectId}:announcement:{$announcementId}",
                60,
            );
            $lockAcquired = $lock->get();
            if (! $lockAcquired) {
                throw new RuntimeException(EditorialErrorCodes::ANNOUNCEMENT_LOCKED);
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
                $domainFailureRecorded = ($result['failure_event_emitted'] ?? false) === true
                    || isset($result['result_id']);
                $errorCode = (string) ($result['error_code'] ?? $result['error'] ?? EditorialErrorCodes::PROVIDER_ERROR);
                $errorCode = EditorialErrorCodes::fromMessage($errorCode);
                throw EditorialGenerationException::permanent($errorCode, $errorCode);
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

            // Fallback GenerationFailed only when service did not already record a domain failure.
            if (! $domainFailureRecorded && $organizationId !== '' && $projectId !== '') {
                try {
                    $eventBus->dispatch(new GenerationFailed(
                        organizationId: $organizationId,
                        projectId: $projectId,
                        announcementId: $announcementId !== '' ? $announcementId : null,
                        errorCode: $errorCode,
                        actorId: $actorId !== '' ? $actorId : null,
                        correlationId: $correlationId,
                    ));
                } catch (Throwable) {
                    // ignore
                }
            }

            if ($this->attempts() >= $this->tries || ! $this->isRetryable($errorCode)) {
                $jobEngine->markFailed($job, $errorCode);
            }

            throw $e instanceof EditorialGenerationException
                ? $e
                : new RuntimeException($errorCode, 0, $e);
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
            $error = $exception ? $this->exceptionErrorCode($exception) : EditorialErrorCodes::EDITORIAL_JOB_FAILED;
            app(JobEngineService::class)->markFailed($job, $error);
        } catch (Throwable) {
        }
    }

    private function exceptionErrorCode(Throwable $throwable): string
    {
        if ($throwable instanceof EditorialGenerationException) {
            return $throwable->errorCode();
        }

        return EditorialErrorCodes::fromMessage(trim($throwable->getMessage()));
    }

    private function isRetryable(string $errorCode): bool
    {
        return EditorialErrorCodes::isRetryable($errorCode);
    }
}
