<?php

namespace StudyMentor\ContentEngine\Admin\Pages;

use StudyMentor\ContentEngine\Data\SourceItemReadRepository;
use StudyMentor\ContentEngine\Platform\PlatformDiagnostics;

defined('ABSPATH') || exit;

final class EditorialWorkspacePage
{
    private $repository;
    private $platformDiagnostics;
    private $viewPath;

    public function __construct(
        SourceItemReadRepository $repository,
        PlatformDiagnostics $platformDiagnostics,
        $viewPath
    ) {
        $this->repository = $repository;
        $this->platformDiagnostics = $platformDiagnostics;
        $this->viewPath = $viewPath;
    }

    public function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html('You do not have permission to view this page.'));
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

        $data = array(
            'title' => 'Editorial Workspace',
            'error_messages' => array(),
            'summary_ok' => $summary['ok'] === true,
            'total' => (int) $summary['total'],
            'new_count' => (int) $summary['new_count'],
            'updated_count' => (int) $summary['updated_count'],
            'last_ingestion_at_utc' => $summary['last_ingestion_at_utc'] !== ''
                ? (string) $summary['last_ingestion_at_utc']
                : '—',
            'spine_ready' => $spineReady,
            'last_batch' => $lastBatch,
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

        require $this->viewPath;
    }
}
