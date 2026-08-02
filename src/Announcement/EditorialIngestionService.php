<?php

namespace StudyMentor\ContentEngine\Announcement;

use StudyMentor\ContentEngine\Acquisition\SourceAcquisitionService;
use StudyMentor\ContentEngine\Collectors\CollectorRegistry;
use StudyMentor\ContentEngine\Platform\PlatformDiagnostics;
use StudyMentor\ContentEngine\Registry\CapabilityRegistry;
use StudyMentor\ContentEngine\Registry\ParserRegistry;

defined('ABSPATH') || exit;

/**
 * Editorial spine façade: acquire → extract → lifecycle.
 * Does not modify acquisition internals; Source Check remains ungated.
 */
final class EditorialIngestionService
{
    private $sourceAcquisitionService;
    private $extractor;
    private $lifecycleService;
    private $capabilityRegistry;
    private $collectorRegistry;
    private $parserRegistry;
    private $platformDiagnostics;
    /** @var LifecycleBatchResult|null */
    private $lastResult;

    public function __construct(
        SourceAcquisitionService $sourceAcquisitionService,
        AnnouncementItemExtractor $extractor,
        AnnouncementLifecycleService $lifecycleService,
        CapabilityRegistry $capabilityRegistry,
        CollectorRegistry $collectorRegistry,
        ParserRegistry $parserRegistry,
        PlatformDiagnostics $platformDiagnostics
    ) {
        $this->sourceAcquisitionService = $sourceAcquisitionService;
        $this->extractor = $extractor;
        $this->lifecycleService = $lifecycleService;
        $this->capabilityRegistry = $capabilityRegistry;
        $this->collectorRegistry = $collectorRegistry;
        $this->parserRegistry = $parserRegistry;
        $this->platformDiagnostics = $platformDiagnostics;
        $this->lastResult = null;
    }

    /**
     * @param int|string $sourceId
     * @return LifecycleBatchResult
     */
    public function ingestFromSource($sourceId)
    {
        $id = (int) $sourceId;

        if ($id <= 0) {
            return $this->reject('invalid_source_id', $id);
        }

        if (!$this->capabilityRegistry->isEnabled(CapabilityRegistry::ACQUISITION)) {
            return $this->reject('capability_disabled', $id);
        }

        if (!$this->startupReady()) {
            return $this->reject('startup_validation_failed', $id);
        }

        $acquisition = $this->sourceAcquisitionService->acquireFromSource($id);

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

        if (!isset($extraction['success']) || $extraction['success'] !== true) {
            $errorCode = isset($extraction['error_code'])
                ? (string) $extraction['error_code']
                : 'extraction_failed';

            return $this->reject($errorCode, $id);
        }

        $candidates = isset($extraction['candidates']) && is_array($extraction['candidates'])
            ? $extraction['candidates']
            : array();

        $result = $this->lifecycleService->apply($candidates);
        $this->lastResult = $result;
        $this->recordIngestionDiagnostics($result);

        return $result;
    }

    /**
     * Apply lifecycle decisions to an explicit candidate set (tests / future callers).
     *
     * @param array<int, AnnouncementCandidate> $candidates
     * @return LifecycleBatchResult
     */
    public function ingestCandidates(array $candidates)
    {
        $result = $this->lifecycleService->apply($candidates);
        $this->lastResult = $result;
        $this->recordIngestionDiagnostics($result);

        return $result;
    }

    /**
     * @return LifecycleBatchResult|null
     */
    public function lastResult()
    {
        return $this->lastResult instanceof LifecycleBatchResult
            ? $this->lastResult
            : $this->lifecycleService->lastBatchResult();
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnosticsStatus()
    {
        $lifecycle = $this->lifecycleService->diagnosticsStatus();

        return array(
            'status' => 'ready',
            'lifecycle' => $lifecycle,
        );
    }

    /**
     * @return bool
     */
    private function startupReady()
    {
        if (!$this->collectorRegistry->has('safe_feed')) {
            return false;
        }

        if (count($this->parserRegistry->all()) < 1) {
            return false;
        }

        if (!$this->capabilityRegistry->isEnabled(CapabilityRegistry::SOURCE_REGISTRY)) {
            return false;
        }

        $sourceTypeMap = $this->collectorRegistry->sourceTypeMap();

        foreach (array('rss', 'atom', 'html') as $sourceType) {
            if (
                !isset($sourceTypeMap[$sourceType])
                || $sourceTypeMap[$sourceType] !== 'safe_feed'
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $errorCode
     * @param int $sourceId
     * @return LifecycleBatchResult
     */
    private function reject($errorCode, $sourceId)
    {
        $result = new LifecycleBatchResult(array(
            'success' => false,
            'error_code' => (string) $errorCode,
            'source_id' => (int) $sourceId,
            'candidates' => 0,
            'new_count' => 0,
            'updated_count' => 0,
            'unchanged_count' => 0,
            'duplicate_count' => 0,
            'decisions' => array(),
        ));
        $this->lastResult = $result;
        $this->recordIngestionDiagnostics($result);

        return $result;
    }

    /**
     * @param LifecycleBatchResult $result
     * @return void
     */
    private function recordIngestionDiagnostics(LifecycleBatchResult $result)
    {
        $this->platformDiagnostics->recordLastIngestion(array(
            'at' => function_exists('current_time')
                ? (string) current_time('mysql', true)
                : gmdate('Y-m-d H:i:s'),
            'ok' => $result->success() === true,
            'source_id' => $result->sourceId(),
            'error_code' => $result->errorCode(),
            'candidates' => $result->candidates(),
            'new_count' => $result->newCount(),
            'updated_count' => $result->updatedCount(),
            'unchanged_count' => $result->unchangedCount(),
            'duplicate_count' => $result->duplicateCount(),
        ));
    }
}
