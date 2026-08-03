<?php

namespace App\Modules\Acquisition\Infrastructure\Jobs;

use App\Application\Services\EventBusService;
use App\Application\Services\JobEngineService;
use App\Infrastructure\Persistence\Models\PlatformJob;
use App\Modules\Acquisition\Application\SourceConnectivityService;
use App\Modules\Acquisition\Domain\Events\SourceCheckCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class SourceConnectivityCheckJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $platformJobId) {}

    public function handle(
        JobEngineService $jobEngine,
        EventBusService $eventBus,
        SourceConnectivityService $connectivity,
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

            $result = $connectivity->check($organizationId, $projectId, $sourceId);
            $eventBus->dispatch(new SourceCheckCompleted(
                $organizationId,
                $projectId,
                $sourceId,
                ($result['success'] ?? false) === true,
                isset($result['error_code']) ? (string) $result['error_code'] : '',
                isset($result['http_status']) ? (int) $result['http_status'] : 0,
                isset($result['duration_ms']) ? (float) $result['duration_ms'] : 0.0,
            ));
            $jobEngine->markCompleted($job, $result);
        } catch (Throwable $throwable) {
            $jobEngine->markFailed(
                $job,
                $throwable->getMessage() !== '' ? $throwable->getMessage() : 'source_check_job_failed',
            );
        }
    }
}
