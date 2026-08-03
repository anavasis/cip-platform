<?php

namespace App\Modules\Acquisition\Infrastructure\Jobs;

use App\Application\Services\EventBusService;
use App\Application\Services\JobEngineService;
use App\Infrastructure\Persistence\Models\PlatformJob;
use App\Modules\Announcement\Application\EditorialIngestionService;
use App\Modules\Announcement\Domain\Events\AnnouncementDiscovered;
use App\Modules\Announcement\Domain\Events\AnnouncementDuplicateDetected;
use App\Modules\Announcement\Domain\Events\AnnouncementUnchanged;
use App\Modules\Announcement\Domain\Events\AnnouncementUpdated;
use App\Modules\Announcement\Domain\LifecycleDecision;
use App\Modules\Announcement\Domain\LifecycleOutcome;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class IngestSourceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $platformJobId) {}

    public function handle(
        JobEngineService $jobEngine,
        EventBusService $eventBus,
        EditorialIngestionService $ingestion,
    ): void {
        $job = PlatformJob::findOrFail($this->platformJobId);
        $job = $jobEngine->markRunning($job);
        $payload = is_array($job->payload) ? $job->payload : [];
        $organizationId = trim((string) ($payload['organization_id'] ?? $job->organization_id ?? ''));
        $projectId = trim((string) ($payload['project_id'] ?? $job->project_id ?? ''));
        $sourceId = trim((string) ($payload['source_id'] ?? ''));

        try {
            if ($organizationId === '' || $projectId === '' || $sourceId === '') {
                throw new RuntimeException('invalid_payload');
            }

            $result = $ingestion
                ->forTenant($organizationId, $projectId)
                ->ingestFromSource($sourceId);

            foreach ($result->decisions() as $decision) {
                $this->dispatchDecision($eventBus, $organizationId, $projectId, $decision);
            }

            if (! $result->success()) {
                $jobEngine->markFailed(
                    $job,
                    $result->errorCode() !== '' ? $result->errorCode() : 'ingestion_failed',
                );

                return;
            }

            $jobEngine->markCompleted($job, $result->toArray());
        } catch (Throwable $throwable) {
            $jobEngine->markFailed(
                $job,
                $throwable->getMessage() !== '' ? $throwable->getMessage() : 'ingestion_job_failed',
            );
        }
    }

    private function dispatchDecision(
        EventBusService $eventBus,
        string $organizationId,
        string $projectId,
        LifecycleDecision $decision,
    ): void {
        $event = match ($decision->outcome()) {
            LifecycleOutcome::NEW_ITEM => new AnnouncementDiscovered(
                $organizationId,
                $projectId,
                $decision->sourceId(),
                $decision->itemId(),
                $decision->identityHash(),
            ),
            LifecycleOutcome::UPDATED => new AnnouncementUpdated(
                $organizationId,
                $projectId,
                $decision->sourceId(),
                $decision->itemId(),
                $decision->identityHash(),
            ),
            LifecycleOutcome::UNCHANGED => new AnnouncementUnchanged(
                $organizationId,
                $projectId,
                $decision->sourceId(),
                $decision->itemId(),
                $decision->identityHash(),
            ),
            LifecycleOutcome::DUPLICATE => new AnnouncementDuplicateDetected(
                $organizationId,
                $projectId,
                $decision->sourceId(),
                $decision->identityHash(),
            ),
            default => null,
        };

        if ($event !== null) {
            $eventBus->dispatch($event);
        }
    }
}
