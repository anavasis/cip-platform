<?php

namespace App\Modules\Acquisition\Infrastructure\Jobs;

use App\Application\Services\JobEngineService;
use App\Infrastructure\Persistence\Models\PlatformJob;
use App\Modules\Acquisition\Application\CapabilityGate;
use App\Modules\Acquisition\Domain\Sources\SourceDueEligibility;
use App\Modules\Acquisition\Infrastructure\Persistence\Models\Source;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class AcquireDueSourcesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $platformJobId) {}

    public function handle(JobEngineService $jobEngine, CapabilityGate $capabilityGate): void
    {
        $platformJob = $jobEngine->markRunning(PlatformJob::findOrFail($this->platformJobId));

        try {
            $payload = is_array($platformJob->payload) ? $platformJob->payload : [];
            $organizationId = trim((string) ($payload['organization_id'] ?? ''));
            $projectId = trim((string) ($payload['project_id'] ?? ''));

            if ($organizationId === '' || $projectId === ''
                || $organizationId !== (string) $platformJob->organization_id
                || $projectId !== (string) $platformJob->project_id) {
                throw new RuntimeException('invalid_payload');
            }

            $scanResult = Cache::lock("acquisition:due:{$projectId}", 300)->get(
                fn (): array => $this->scanProject(
                    $jobEngine,
                    $capabilityGate,
                    $organizationId,
                    $projectId,
                ),
            );

            if ($scanResult === false) {
                $scanResult = [
                    'sources_due' => 0,
                    'jobs_dispatched' => 0,
                    'platform_job_ids' => [],
                    'overlap_skipped' => true,
                ];
            }

            $jobEngine->markCompleted($platformJob, $scanResult);
        } catch (Throwable $throwable) {
            $jobEngine->markFailed(
                $platformJob,
                $throwable->getMessage() !== '' ? $throwable->getMessage() : 'due_source_dispatch_failed',
            );

            throw $throwable;
        }
    }

    /** @return array<string, mixed> */
    private function scanProject(
        JobEngineService $jobEngine,
        CapabilityGate $capabilityGate,
        string $organizationId,
        string $projectId,
    ): array {
        if (! $this->capabilitiesEnabled($capabilityGate, $organizationId, $projectId)) {
            return [
                'sources_due' => 0,
                'jobs_dispatched' => 0,
                'platform_job_ids' => [],
                'capability_enabled' => false,
            ];
        }

        $dueCount = 0;
        $dispatchedIds = [];

        $query = Source::query()
            ->where('organization_id', $organizationId)
            ->where('project_id', $projectId);
        SourceDueEligibility::constrainEligible($query)
            ->orderBy('id')
            ->chunkById(100, function ($sources) use (
                $jobEngine,
                $capabilityGate,
                $organizationId,
                $projectId,
                &$dueCount,
                &$dispatchedIds,
            ): void {
                foreach ($sources as $source) {
                    if (! SourceDueEligibility::isDue($source)) {
                        continue;
                    }

                    $dueCount++;
                    $lockKey = "acquisition:project:{$projectId}:source:{$source->id}";
                    $childJobId = Cache::lock($lockKey, 30)->get(function () use (
                        $jobEngine,
                        $capabilityGate,
                        $organizationId,
                        $projectId,
                        $source,
                    ): string|false {
                        $fresh = Source::query()
                            ->where('organization_id', $organizationId)
                            ->where('project_id', $projectId)
                            ->whereKey($source->id)
                            ->first();

                        if ($fresh === null || ! $fresh->enabled || $fresh->manual_only
                            || ! SourceDueEligibility::isDue($fresh)
                            || ! $this->capabilitiesEnabled(
                                $capabilityGate,
                                $organizationId,
                                $projectId,
                            )) {
                            return false;
                        }

                        return $jobEngine->create(
                            'acquisition.acquire_source',
                            $organizationId,
                            $projectId,
                            [
                                'organization_id' => $organizationId,
                                'project_id' => $projectId,
                                'source_id' => (string) $fresh->id,
                                'trigger' => 'schedule',
                                'require_due' => true,
                            ],
                        )->id;
                    });

                    if (is_string($childJobId) && $childJobId !== '') {
                        AcquireSourceJob::dispatch($childJobId);
                        $dispatchedIds[] = $childJobId;
                    }
                }
            });

        return [
            'sources_due' => $dueCount,
            'jobs_dispatched' => count($dispatchedIds),
            'platform_job_ids' => $dispatchedIds,
            'capability_enabled' => true,
        ];
    }

    private function capabilitiesEnabled(
        CapabilityGate $capabilityGate,
        string $organizationId,
        string $projectId,
    ): bool {
        return $capabilityGate->isEnabledFor(
            CapabilityGate::ACQUISITION,
            $organizationId,
            $projectId,
        ) && $capabilityGate->isEnabledFor(
            CapabilityGate::SOURCE_REGISTRY,
            $organizationId,
            $projectId,
        );
    }
}
