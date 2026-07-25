<?php

namespace StudyMentor\ContentEngine\Admin;

use StudyMentor\ContentEngine\Core\FeatureFlags;
use StudyMentor\ContentEngine\Data\SourceItemIntakeService;

defined('ABSPATH') || exit;

final class SourceItemActionHandler
{
    private $featureFlags;
    private $intakeService;

    public function __construct(FeatureFlags $featureFlags, SourceItemIntakeService $intakeService)
    {
        $this->featureFlags = $featureFlags;
        $this->intakeService = $intakeService;
    }

    public function register()
    {
        if (!$this->featureFlags->isEnabled('source_registry')) {
            return;
        }

        add_action('admin_post_smce_source_item_confirm', array($this, 'handleConfirm'));
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

        $sourceId = isset($_POST['source_id']) ? (int) $_POST['source_id'] : 0;

        if ($sourceId <= 0) {
            $this->redirect('invalid_source');
        }

        if (
            !isset($_POST['smce_manual_nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash((string) $_POST['smce_manual_nonce'])),
                'smce_source_item_confirm'
            )
        ) {
            $this->redirect('nonce');
        }

        $rawJson = isset($_POST['smce_manual_json']) ? wp_unslash((string) $_POST['smce_manual_json']) : '';

        $result = $this->intakeService->confirm($sourceId, $rawJson);

        $code = isset($result['result']) ? (string) $result['result'] : 'validation_failed';
        $inserted = isset($result['inserted']) ? (int) $result['inserted'] : 0;
        $duplicate = isset($result['duplicate']) ? (int) $result['duplicate'] : 0;

        $this->redirect($code === 'ok' ? 'confirmed' : $code, $inserted, $duplicate);
    }

    private function redirect($noticeCode, $inserted = 0, $duplicate = 0)
    {
        $args = array(
            'page' => 'smce-manual-announcements',
            'smce_manual_notice' => sanitize_key((string) $noticeCode),
            'smce_manual_inserted' => (int) $inserted,
            'smce_manual_duplicate' => (int) $duplicate,
        );

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }
}
