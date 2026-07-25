<?php

namespace StudyMentor\ContentEngine\Admin;

use StudyMentor\ContentEngine\Core\FeatureFlags;
use StudyMentor\ContentEngine\Data\SourceCatalogBulkService;

defined('ABSPATH') || exit;

final class SourceCatalogActionHandler
{
    private $featureFlags;
    private $bulkService;

    public function __construct(FeatureFlags $featureFlags, SourceCatalogBulkService $bulkService)
    {
        $this->featureFlags = $featureFlags;
        $this->bulkService = $bulkService;
    }

    public function register()
    {
        if (!$this->featureFlags->isEnabled('source_registry')) {
            return;
        }

        add_action('admin_post_smce_source_catalog_confirm', array($this, 'handleConfirm'));
    }

    public function handleConfirm()
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('permission');
        }

        if (!$this->featureFlags->isEnabled('source_registry')) {
            $this->redirect('permission');
        }

        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            $this->redirect('permission');
        }

        if (
            !isset($_POST['smce_bulk_sources_nonce'])
            || !is_string($_POST['smce_bulk_sources_nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['smce_bulk_sources_nonce'])),
                'smce_source_catalog_confirm'
            )
        ) {
            $this->redirect('nonce');
        }

        if (!isset($_POST['smce_bulk_json']) || !is_string($_POST['smce_bulk_json'])) {
            $this->redirect('validation_failed');
        }

        $rawJson = wp_unslash($_POST['smce_bulk_json']);
        $result = $this->bulkService->confirm($rawJson);

        $code = isset($result['result']) ? (string) $result['result'] : 'validation_failed';
        $inserted = isset($result['inserted']) ? (int) $result['inserted'] : 0;
        $duplicate = isset($result['duplicate']) ? (int) $result['duplicate'] : 0;
        $invalid = isset($result['invalid']) ? (int) $result['invalid'] : 0;

        $allowedCodes = array('confirmed', 'validation_failed', 'insert_failed');

        if (!in_array($code, $allowedCodes, true)) {
            $code = 'validation_failed';
        }

        $this->redirect($code, $inserted, $duplicate, $invalid);
    }

    private function redirect($noticeCode, $inserted = 0, $duplicate = 0, $invalid = 0)
    {
        $args = array(
            'page' => 'smce-bulk-sources',
            'smce_bulk_notice' => sanitize_key((string) $noticeCode),
            'smce_bulk_inserted' => (int) $inserted,
            'smce_bulk_duplicate' => (int) $duplicate,
            'smce_bulk_invalid' => (int) $invalid,
        );

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }
}
