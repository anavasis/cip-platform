<?php

namespace StudyMentor\ContentEngine\Admin\Pages;

use StudyMentor\ContentEngine\Core\FeatureFlags;
use StudyMentor\ContentEngine\Data\SourceItemIntakeService;
use StudyMentor\ContentEngine\Data\SourceRepository;

defined('ABSPATH') || exit;

final class ManualAnnouncementsPage
{
    private $featureFlags;
    private $sourceRepository;
    private $intakeService;
    private $viewPath;

    public function __construct(
        FeatureFlags $featureFlags,
        SourceRepository $sourceRepository,
        SourceItemIntakeService $intakeService,
        $viewPath
    ) {
        $this->featureFlags = $featureFlags;
        $this->sourceRepository = $sourceRepository;
        $this->intakeService = $intakeService;
        $this->viewPath = $viewPath;
    }

    public function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html('You do not have permission to view this page.'));
        }

        if (!$this->featureFlags->isEnabled('source_registry')) {
            wp_die(esc_html('The Sources Registry is not enabled.'));
        }

        $submittedSourceId = 0;
        $submittedRawJson = '';
        $previewError = '';
        $previewResult = null;

        $isPreviewRequest = isset($_SERVER['REQUEST_METHOD'])
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['smce_manual_preview']);

        if ($isPreviewRequest) {
            $submittedSourceId = isset($_POST['source_id']) ? (int) $_POST['source_id'] : 0;
            $submittedRawJson = isset($_POST['smce_manual_json'])
                ? wp_unslash((string) $_POST['smce_manual_json'])
                : '';

            if (!current_user_can('manage_options')) {
                $previewError = 'permission';
            } elseif (!$this->featureFlags->isEnabled('source_registry')) {
                $previewError = 'permission';
            } elseif (
                !isset($_POST['smce_manual_nonce'])
                || !wp_verify_nonce(
                    sanitize_text_field(wp_unslash((string) $_POST['smce_manual_nonce'])),
                    'smce_manual_preview'
                )
            ) {
                $previewError = 'nonce';
            } elseif ($submittedSourceId <= 0) {
                $previewError = 'invalid_source';
            } else {
                $previewResult = $this->intakeService->preview($submittedSourceId, $submittedRawJson);
            }
        }

        $manualSources = $this->loadManualOnlySources();

        $noticeCode = isset($_GET['smce_manual_notice'])
            ? sanitize_key(wp_unslash((string) $_GET['smce_manual_notice']))
            : '';
        $insertedCount = isset($_GET['smce_manual_inserted']) ? (int) $_GET['smce_manual_inserted'] : 0;
        $duplicateCount = isset($_GET['smce_manual_duplicate']) ? (int) $_GET['smce_manual_duplicate'] : 0;

        $data = array(
            'title' => 'Manual Announcements',
            'page_url' => admin_url('admin.php?page=smce-manual-announcements'),
            'confirm_action_url' => admin_url('admin-post.php'),
            'preview_nonce_field' => 'smce_manual_nonce',
            'preview_nonce_action' => 'smce_manual_preview',
            'confirm_nonce_field' => 'smce_manual_nonce',
            'confirm_nonce_action' => 'smce_source_item_confirm',
            'manual_sources' => $manualSources,
            'submitted_source_id' => $submittedSourceId,
            'submitted_raw_json' => $submittedRawJson,
            'preview_error_message' => $this->mapPreviewErrorMessage($previewError),
            'preview_result' => $this->buildPreviewDisplay($previewResult),
            'notice_message' => $this->mapNoticeMessage($noticeCode, $insertedCount, $duplicateCount),
        );

        require $this->viewPath;
    }

    private function loadManualOnlySources()
    {
        $sources = $this->sourceRepository->findAll();
        $manualSources = array();

        foreach ($sources as $source) {
            if (!is_array($source) || !isset($source['manual_only']) || (int) $source['manual_only'] !== 1) {
                continue;
            }

            $manualSources[] = array(
                'id' => (int) $source['id'],
                'name' => (string) $source['name'],
            );
        }

        return $manualSources;
    }

    private function buildPreviewDisplay($previewResult)
    {
        if ($previewResult === null) {
            return null;
        }

        if ($previewResult['source_error'] !== '') {
            return array(
                'top_level_error' => $this->mapSourceErrorMessage($previewResult['source_error']),
                'rows' => array(),
                'show_confirm' => false,
                'source_id' => 0,
            );
        }

        if ($previewResult['payload_error'] !== '') {
            return array(
                'top_level_error' => $this->mapPayloadErrorMessage($previewResult['payload_error']),
                'rows' => array(),
                'show_confirm' => false,
                'source_id' => 0,
            );
        }

        $rows = array();

        foreach ($previewResult['records'] as $record) {
            $rows[] = array(
                'index' => (int) $record['index'],
                'status_label' => $this->mapStatusLabel($record['status']),
                'message' => $this->mapRecordMessage($record['message']),
                'date' => (string) $record['date'],
                'category' => (string) $record['category'],
                'title' => (string) $record['title'],
                'canonical_url' => (string) $record['canonical_url'],
            );
        }

        return array(
            'top_level_error' => '',
            'rows' => $rows,
            'show_confirm' => $previewResult['all_valid'] === true,
            'source_id' => isset($previewResult['source']['id']) ? (int) $previewResult['source']['id'] : 0,
        );
    }

    private function mapStatusLabel($status)
    {
        $labels = array(
            'new' => 'New',
            'duplicate_existing' => 'Existing duplicate',
            'duplicate_batch' => 'Duplicate in batch',
            'invalid' => 'Invalid',
        );

        return isset($labels[$status]) ? $labels[$status] : 'Invalid';
    }

    private function mapRecordMessage($code)
    {
        $messages = array(
            'valid' => 'Valid.',
            'duplicate_existing' => 'This item already exists for the selected source and will be skipped.',
            'duplicate_in_batch' => 'This item duplicates another item in the same submission and will be skipped.',
            'not_object' => 'Each record must be a JSON object.',
            'unexpected_key' => 'The record contains an unexpected field.',
            'missing_required_key' => 'The record is missing a required field (title, url, or date).',
            'invalid_title' => 'The title is required, must be plain text, and at most 500 characters.',
            'invalid_category' => 'The category must be plain text and at most 150 characters.',
            'invalid_date' => 'The date must be a valid calendar date in YYYY-MM-DD format.',
            'invalid_url' => 'The URL is missing, malformed, or uses an unsupported scheme.',
            'url_too_long' => 'The URL exceeds the maximum allowed length.',
            'url_credentials' => 'The URL must not contain credentials.',
            'invalid_host' => 'The URL host could not be validated.',
            'domain_not_allowed' => 'The URL host is not in the source allowed domains list.',
            'payload_encoding_failed' => 'The record could not be encoded for storage.',
        );

        return isset($messages[$code]) ? $messages[$code] : 'This record failed validation.';
    }

    private function mapSourceErrorMessage($code)
    {
        $messages = array(
            'invalid_source_id' => 'Please select a valid source.',
            'source_not_found' => 'The selected source could not be found.',
            'source_not_manual_only' => 'The selected source is not a manual-only source.',
            'source_invalid_allowed_domains' => 'The selected source does not have a valid allowed domains list.',
        );

        return isset($messages[$code]) ? $messages[$code] : 'The selected source is invalid.';
    }

    private function mapPayloadErrorMessage($code)
    {
        $messages = array(
            'invalid_payload' => 'The submitted JSON payload is invalid.',
            'empty_payload' => 'Please paste a JSON array before previewing.',
            'payload_too_large' => 'The submitted JSON payload is too large.',
            'invalid_utf8' => 'The submitted JSON payload is not valid UTF-8.',
            'invalid_json' => 'The submitted text is not valid JSON.',
            'not_array' => 'The JSON payload must be a top-level array.',
            'not_list' => 'The JSON payload must be a numerically indexed array.',
            'empty_batch' => 'The JSON array must contain at least one record.',
            'too_many_records' => 'The JSON array must contain no more than 25 records.',
        );

        return isset($messages[$code]) ? $messages[$code] : 'The submitted JSON payload is invalid.';
    }

    private function mapPreviewErrorMessage($code)
    {
        $messages = array(
            'permission' => 'You do not have permission to perform that action.',
            'nonce' => 'Security verification failed. Please try again.',
            'invalid_source' => 'Please select a valid source before previewing.',
        );

        return isset($messages[$code]) ? $messages[$code] : '';
    }

    private function mapNoticeMessage($code, $inserted, $duplicate)
    {
        $insertedInt = (int) $inserted;
        $duplicateInt = (int) $duplicate;

        switch ($code) {
            case 'confirmed':
                return sprintf(
                    'Import complete. Inserted: %d. Skipped duplicates: %d.',
                    $insertedInt,
                    $duplicateInt
                );
            case 'insert_failed':
                return sprintf(
                    'Import stopped after an unexpected error. Inserted before stopping: %d. Skipped duplicates: %d.',
                    $insertedInt,
                    $duplicateInt
                );
            case 'validation_failed':
                return 'The submitted data failed validation. Nothing was imported.';
            case 'permission':
                return 'You do not have permission to perform that action.';
            case 'nonce':
                return 'Security verification failed. Please try again.';
            case 'invalid_source':
                return 'The submitted source identifier is invalid.';
            default:
                return '';
        }
    }
}
