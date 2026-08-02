<?php

namespace StudyMentor\ContentEngine\Admin\Pages;

use StudyMentor\ContentEngine\Announcement\EditorialIngestionService;
use StudyMentor\ContentEngine\Data\SourceItemReadRepository;
use StudyMentor\ContentEngine\Data\SourceRepository;
use StudyMentor\ContentEngine\Platform\PlatformDiagnostics;

defined('ABSPATH') || exit;

final class EditorialWorkspacePage
{
    private $repository;
    private $sourceRepository;
    private $ingestionService;
    private $platformDiagnostics;
    private $viewPath;

    public function __construct(
        SourceItemReadRepository $repository,
        SourceRepository $sourceRepository,
        EditorialIngestionService $ingestionService,
        PlatformDiagnostics $platformDiagnostics,
        $viewPath
    ) {
        $this->repository = $repository;
        $this->sourceRepository = $sourceRepository;
        $this->ingestionService = $ingestionService;
        $this->platformDiagnostics = $platformDiagnostics;
        $this->viewPath = $viewPath;
    }

    public function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html('You do not have permission to view this page.'));
        }

        $ingestionNotice = null;
        $ingestionError = null;
        $requestMethod = isset($_SERVER['REQUEST_METHOD'])
            && is_string($_SERVER['REQUEST_METHOD'])
            ? strtoupper($_SERVER['REQUEST_METHOD'])
            : '';

        if ($requestMethod === 'POST' && isset($_POST['smce_editorial_ingest'])) {
            $ingestOutcome = $this->handleIngestPost();
            if ($ingestOutcome['ok'] === true) {
                $ingestionNotice = $ingestOutcome['message'];
            } else {
                $ingestionError = $ingestOutcome['message'];
            }
        }

        $summary = $this->repository->findEditorialSummary();
        $platform = $this->platformDiagnostics->collect();
        $spineReady = isset($platform['confirmations']['announcement_lifecycle'])
            ? (string) $platform['confirmations']['announcement_lifecycle']
            : 'Not ready';
        $lastBatch = null;

        if (
            isset($platform['announcement_lifecycle']['last_batch'])
            && is_array($platform['announcement_lifecycle']['last_batch'])
        ) {
            $lastBatch = $platform['announcement_lifecycle']['last_batch'];
        }

        $sourceOptions = array();
        $sources = $this->sourceRepository->findAll();
        foreach ($sources as $source) {
            if (!is_array($source) || !isset($source['id'])) {
                continue;
            }

            $sourceOptions[] = array(
                'id' => (int) $source['id'],
                'name' => isset($source['name']) ? (string) $source['name'] : '',
                'enabled' => isset($source['enabled']) ? (int) $source['enabled'] : 0,
            );
        }

        $data = array(
            'title' => 'Editorial Workspace',
            'error_messages' => array(),
            'success_messages' => array(),
            'summary_ok' => $summary['ok'] === true,
            'total' => (int) $summary['total'],
            'new_count' => (int) $summary['new_count'],
            'updated_count' => (int) $summary['updated_count'],
            'last_ingestion_at_utc' => $summary['last_ingestion_at_utc'] !== ''
                ? (string) $summary['last_ingestion_at_utc']
                : '—',
            'spine_ready' => $spineReady,
            'last_batch' => $lastBatch,
            'last_ingestion' => isset($platform['last_ingestion']) && is_array($platform['last_ingestion'])
                ? $platform['last_ingestion']
                : null,
            'last_generation' => isset($platform['last_generation']) && is_array($platform['last_generation'])
                ? $platform['last_generation']
                : null,
            'source_options' => $sourceOptions,
            'ingest_form_url' => add_query_arg(
                array('page' => 'smce-editorial'),
                admin_url('admin.php')
            ),
            'announcements_url' => add_query_arg(
                array('page' => 'smce-editorial-announcements'),
                admin_url('admin.php')
            ),
            'queue_url' => add_query_arg(
                array('page' => 'smce-editorial-queue'),
                admin_url('admin.php')
            ),
            'queue_new_url' => add_query_arg(
                array('page' => 'smce-editorial-queue', 'status' => 'new'),
                admin_url('admin.php')
            ),
            'queue_updated_url' => add_query_arg(
                array('page' => 'smce-editorial-queue', 'status' => 'updated'),
                admin_url('admin.php')
            ),
        );

        if ($summary['ok'] !== true) {
            $data['error_messages'][] = 'Editorial summary counts could not be loaded. Please try again.';
        }

        if ($ingestionError !== null) {
            $data['error_messages'][] = $ingestionError;
        }

        if ($ingestionNotice !== null) {
            $data['success_messages'][] = $ingestionNotice;
        }

        require $this->viewPath;
    }

    /**
     * @return array{ok:bool,message:string}
     */
    private function handleIngestPost()
    {
        if (!current_user_can('manage_options')) {
            return array(
                'ok' => false,
                'message' => 'You do not have permission to run editorial ingestion.',
            );
        }

        $sourceId = isset($_POST['source_id']) ? (int) $_POST['source_id'] : 0;
        if ($sourceId <= 0) {
            return array(
                'ok' => false,
                'message' => 'Select a valid source before running editorial ingestion.',
            );
        }

        if (
            !isset($_POST['smce_editorial_ingest_nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash((string) $_POST['smce_editorial_ingest_nonce'])),
                'smce_editorial_ingest'
            )
        ) {
            return array(
                'ok' => false,
                'message' => 'Security verification failed. Please try again.',
            );
        }

        $result = $this->ingestionService->ingestFromSource($sourceId);
        $payload = array(
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
        );
        $this->platformDiagnostics->recordLastIngestion($payload);

        if ($result->success() !== true) {
            $code = $result->errorCode() !== '' ? $result->errorCode() : 'ingestion_failed';

            return array(
                'ok' => false,
                'message' => 'Editorial ingestion failed (' . $code . ').',
            );
        }

        return array(
            'ok' => true,
            'message' => sprintf(
                'Editorial ingestion completed for source #%d: new=%d, updated=%d, unchanged=%d, duplicate=%d.',
                $result->sourceId(),
                $result->newCount(),
                $result->updatedCount(),
                $result->unchangedCount(),
                $result->duplicateCount()
            ),
        );
    }
}
