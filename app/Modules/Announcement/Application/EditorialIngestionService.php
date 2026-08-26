<?php

namespace App\Modules\Announcement\Application;

use App\Modules\Acquisition\Application\SourceAcquisitionService;
use App\Modules\Announcement\Domain\AnnouncementCandidate;
use App\Modules\Announcement\Domain\AnnouncementItemExtractor;
use App\Modules\Announcement\Domain\Contracts\CapabilityGateInterface;
use App\Modules\Announcement\Domain\Contracts\CollectorRegistryInterface;
use App\Modules\Announcement\Domain\Contracts\IngestionDiagnosticsInterface;
use App\Modules\Announcement\Domain\Contracts\ParserRegistryInterface;
use App\Modules\Announcement\Domain\LifecycleBatchResult;
use App\Modules\Announcement\Infrastructure\Persistence\Models\Announcement;
use App\Modules\Intelligence\Application\EntityBindingService;

/**
 * Editorial spine facade: acquire, extract, then apply lifecycle decisions.
 */
final class EditorialIngestionService
{
    private ?LifecycleBatchResult $lastResult = null;

    private string $organizationId = '';

    private string $projectId = '';

    public function __construct(
        private readonly SourceAcquisitionService $sourceAcquisitionService,
        private readonly AnnouncementItemExtractor $extractor,
        private readonly AnnouncementLifecycleService $lifecycleService,
        private readonly CapabilityGateInterface $capabilityGate,
        private readonly CollectorRegistryInterface $collectorRegistry,
        private readonly ParserRegistryInterface $parserRegistry,
        private readonly IngestionDiagnosticsInterface $diagnostics,
        private readonly EntityBindingService $entityBindingService,
    ) {}

    public function forTenant(string $organizationId, string $projectId): self
    {
        $this->organizationId = trim($organizationId);
        $this->projectId = trim($projectId);
        $this->lifecycleService->forTenant($this->organizationId, $this->projectId);

        return $this;
    }

    public function ingestFromSource(string $sourceId): LifecycleBatchResult
    {
        $id = trim($sourceId);

        if ($id === '') {
            return $this->reject('invalid_source_id', $id);
        }

        if ($this->organizationId === '' || $this->projectId === '') {
            return $this->reject('tenant_context_missing', $id);
        }

        $tenantGate = $this->tenantGate();

        if (! $tenantGate->isEnabled(CapabilityGateInterface::ACQUISITION)) {
            return $this->reject('capability_disabled', $id);
        }

        if (! $this->startupReady($tenantGate)) {
            return $this->reject('startup_validation_failed', $id);
        }

        $acquisition = $this->sourceAcquisitionService->acquireFromSource(
            $this->organizationId,
            $this->projectId,
            $id,
        );

        if ($acquisition->success() !== true) {
            $errorCode = $acquisition->errorCode() !== ''
                ? $acquisition->errorCode()
                : 'acquisition_failed';

            return $this->reject($errorCode, $id);
        }

        $evidence = $acquisition->evidence();
        $body = $evidence !== null ? $evidence->body() : '';
        $sourceType = $evidence !== null ? $evidence->sourceType() : '';
        $parserProfile = $evidence !== null ? $evidence->parserProfile() : '';
        $extraction = $this->extractor->extract($body, $id, $sourceType, $parserProfile);

        if (! isset($extraction['success']) || $extraction['success'] !== true) {
            $errorCode = isset($extraction['error_code'])
                ? (string) $extraction['error_code']
                : 'extraction_failed';

            return $this->reject($errorCode, $id);
        }

        $candidates = isset($extraction['candidates']) && is_array($extraction['candidates'])
            ? $extraction['candidates']
            : [];
        $result = $this->lifecycleService->apply($candidates);
        $this->lastResult = $result;
        $this->bindEntitiesFromBatch($result);
        $this->recordIngestionDiagnostics($result);

        return $result;
    }

    /**
     * Apply lifecycle decisions to an explicit candidate set.
     *
     * @param  array<int, AnnouncementCandidate>  $candidates
     */
    public function ingestCandidates(array $candidates): LifecycleBatchResult
    {
        $result = $this->lifecycleService->apply($candidates);
        $this->lastResult = $result;
        $this->bindEntitiesFromBatch($result);
        $this->recordIngestionDiagnostics($result);

        return $result;
    }

    public function lastResult(): ?LifecycleBatchResult
    {
        return $this->lastResult ?? $this->lifecycleService->lastBatchResult();
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnosticsStatus(): array
    {
        return [
            'status' => 'ready',
            'lifecycle' => $this->lifecycleService->diagnosticsStatus(),
        ];
    }

    private function tenantGate(): CapabilityGateInterface
    {
        if ($this->capabilityGate instanceof \App\Modules\Acquisition\Application\CapabilityGate) {
            return $this->capabilityGate->forTenant($this->organizationId, $this->projectId);
        }

        return $this->capabilityGate;
    }

    private function startupReady(CapabilityGateInterface $tenantGate): bool
    {
        if (! $this->collectorRegistry->has('safe_feed')) {
            return false;
        }

        if (count($this->parserRegistry->all()) < 1) {
            return false;
        }

        if (! $tenantGate->isEnabled(CapabilityGateInterface::SOURCE_REGISTRY)) {
            return false;
        }

        $sourceTypeMap = $this->collectorRegistry->sourceTypeMap();

        foreach (['rss', 'atom', 'html'] as $sourceType) {
            if (
                ! isset($sourceTypeMap[$sourceType])
                || $sourceTypeMap[$sourceType] !== 'safe_feed'
            ) {
                return false;
            }
        }

        return true;
    }

    private function reject(string $errorCode, string $sourceId): LifecycleBatchResult
    {
        $result = new LifecycleBatchResult([
            'success' => false,
            'error_code' => $errorCode,
            'source_id' => $sourceId,
            'candidates' => 0,
            'new_count' => 0,
            'updated_count' => 0,
            'unchanged_count' => 0,
            'duplicate_count' => 0,
            'decisions' => [],
        ]);
        $this->lastResult = $result;
        $this->recordIngestionDiagnostics($result);

        return $result;
    }

    private function bindEntitiesFromBatch(LifecycleBatchResult $result): void
    {
        if ($result->success() !== true) {
            return;
        }

        foreach ($result->decisions() as $decision) {
            $itemId = trim($decision->itemId());
            if ($itemId === '') {
                continue;
            }

            $announcement = Announcement::query()->find($itemId);
            if ($announcement === null) {
                continue;
            }

            if ((string) $announcement->organization_id !== $this->organizationId
                || (string) $announcement->project_id !== $this->projectId) {
                continue;
            }

            $this->entityBindingService->bindAnnouncement($announcement);
        }
    }

    private function recordIngestionDiagnostics(LifecycleBatchResult $result): void
    {
        $this->diagnostics->record([
            'organization_id' => $this->organizationId,
            'project_id' => $this->projectId,
            'at' => gmdate('Y-m-d H:i:s'),
            'ok' => $result->success(),
            'source_id' => $result->sourceId(),
            'error_code' => $result->errorCode(),
            'candidates' => $result->candidates(),
            'new_count' => $result->newCount(),
            'updated_count' => $result->updatedCount(),
            'unchanged_count' => $result->unchangedCount(),
            'duplicate_count' => $result->duplicateCount(),
        ]);
    }
}
