<?php

namespace StudyMentor\ContentEngine\Admin\Pages;

use StudyMentor\ContentEngine\Core\FeatureFlags;
use StudyMentor\ContentEngine\Data\SourceCatalogBulkService;

defined('ABSPATH') || exit;

final class BulkSourcesPage
{
    private $featureFlags;
    private $bulkService;
    private $viewPath;

    public function __construct(
        FeatureFlags $featureFlags,
        SourceCatalogBulkService $bulkService,
        $viewPath
    ) {
        $this->featureFlags = $featureFlags;
        $this->bulkService = $bulkService;
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

        $submittedRawJson = '';
        $previewError = '';
        $previewResult = null;

        $isPreviewRequest = isset($_SERVER['REQUEST_METHOD'])
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['smce_bulk_sources_preview']);

        if ($isPreviewRequest) {
            if (!current_user_can('manage_options')) {
                $previewError = 'permission';
            } elseif (!$this->featureFlags->isEnabled('source_registry')) {
                $previewError = 'permission';
            } elseif (
                !isset($_POST['smce_bulk_sources_nonce'])
                || !is_string($_POST['smce_bulk_sources_nonce'])
                || !wp_verify_nonce(
                    sanitize_text_field(wp_unslash($_POST['smce_bulk_sources_nonce'])),
                    'smce_bulk_sources_preview'
                )
            ) {
                $previewError = 'nonce';
            } elseif (!isset($_POST['smce_bulk_json']) || !is_string($_POST['smce_bulk_json'])) {
                $previewError = 'invalid_payload';
                $submittedRawJson = '';
            } else {
                $submittedRawJson = wp_unslash($_POST['smce_bulk_json']);
                $previewResult = $this->bulkService->preview($submittedRawJson);
            }
        }

        $noticeCode = isset($_GET['smce_bulk_notice']) && is_string($_GET['smce_bulk_notice'])
            ? sanitize_key(wp_unslash($_GET['smce_bulk_notice']))
            : '';
        $insertedCount = isset($_GET['smce_bulk_inserted']) ? (int) $_GET['smce_bulk_inserted'] : 0;
        $duplicateCount = isset($_GET['smce_bulk_duplicate']) ? (int) $_GET['smce_bulk_duplicate'] : 0;
        $invalidCount = isset($_GET['smce_bulk_invalid']) ? (int) $_GET['smce_bulk_invalid'] : 0;

        $data = array(
            'title' => 'Bulk Sources',
            'page_url' => admin_url('admin.php?page=smce-bulk-sources'),
            'confirm_action_url' => admin_url('admin-post.php'),
            'preview_nonce_field' => 'smce_bulk_sources_nonce',
            'preview_nonce_action' => 'smce_bulk_sources_preview',
            'confirm_nonce_field' => 'smce_bulk_sources_nonce',
            'confirm_nonce_action' => 'smce_source_catalog_confirm',
            'json_example' => $this->jsonExample(),
            'submitted_raw_json' => $submittedRawJson,
            'preview_error_message' => $this->mapPreviewErrorMessage($previewError),
            'preview_result' => $this->buildPreviewDisplay($previewResult),
            'notice_message' => $this->mapNoticeMessage(
                $noticeCode,
                $insertedCount,
                $duplicateCount,
                $invalidCount
            ),
        );

        require $this->viewPath;
    }

    private function jsonExample()
    {
        return '[' . "\n"
            . '  {' . "\n"
            . '    "slug": "example-source",' . "\n"
            . '    "name": "Example Source",' . "\n"
            . '    "source_type": "html",' . "\n"
            . '    "feed_url": "https://example.gov/path",' . "\n"
            . '    "base_url": "https://example.gov",' . "\n"
            . '    "allowed_domains": ["example.gov"],' . "\n"
            . '    "parser_profile": null' . "\n"
            . '  }' . "\n"
            . ']';
    }

    private function buildPreviewDisplay($previewResult)
    {
        if ($previewResult === null) {
            return null;
        }

        if (empty($previewResult['structurally_valid'])) {
            return array(
                'top_level_error' => $this->mapPayloadErrorMessage(
                    isset($previewResult['code']) ? (string) $previewResult['code'] : 'invalid_payload'
                ),
                'rows' => array(),
                'show_confirm' => false,
                'summary' => '',
            );
        }

        $rows = array();

        if (isset($previewResult['rows']) && is_array($previewResult['rows'])) {
            foreach ($previewResult['rows'] as $record) {
                if (!is_array($record)) {
                    continue;
                }

                $rows[] = array(
                    'index' => isset($record['index']) ? (int) $record['index'] : 0,
                    'status_label' => $this->mapStatusLabel(
                        isset($record['status']) ? (string) $record['status'] : 'invalid'
                    ),
                    'message' => $this->mapRecordMessage(
                        isset($record['message']) ? (string) $record['message'] : ''
                    ),
                    'slug' => isset($record['slug']) ? (string) $record['slug'] : '',
                    'name' => isset($record['name']) ? (string) $record['name'] : '',
                    'source_type' => isset($record['source_type']) ? (string) $record['source_type'] : '',
                    'feed_url' => isset($record['feed_url']) ? (string) $record['feed_url'] : '',
                    'allowed_domains' => isset($record['allowed_domains'])
                        ? (string) $record['allowed_domains']
                        : '',
                    'parser_profile' => isset($record['parser_profile'])
                        ? (string) $record['parser_profile']
                        : '',
                );
            }
        }

        $ready = isset($previewResult['ready']) ? (int) $previewResult['ready'] : 0;
        $duplicate = isset($previewResult['duplicate']) ? (int) $previewResult['duplicate'] : 0;
        $invalid = isset($previewResult['invalid']) ? (int) $previewResult['invalid'] : 0;
        $allValid = !empty($previewResult['all_valid']);
        $structurallyValid = !empty($previewResult['structurally_valid']);

        return array(
            'top_level_error' => '',
            'rows' => $rows,
            'show_confirm' => $structurallyValid && $allValid && $ready > 0,
            'summary' => sprintf(
                'Total: %d. Ready: %d. Duplicate: %d. Invalid: %d.',
                isset($previewResult['total']) ? (int) $previewResult['total'] : 0,
                $ready,
                $duplicate,
                $invalid
            ),
        );
    }

    private function mapStatusLabel($status)
    {
        $labels = array(
            'ready' => 'Ready',
            'duplicate_existing' => 'Existing duplicate',
            'duplicate_batch' => 'Duplicate in batch',
            'invalid' => 'Invalid',
        );

        return isset($labels[$status]) ? $labels[$status] : 'Invalid';
    }

    private function mapRecordMessage($code)
    {
        $messages = array(
            'ready' => 'Ready to create as disabled and manual-only.',
            'duplicate_existing' => 'A source with this slug or feed URL already exists and will be skipped.',
            'duplicate_batch' => 'This record duplicates another record in the same submission and will be skipped.',
            'not_object' => 'Each record must be a JSON object.',
            'unexpected_key' => 'The record contains an unexpected field.',
            'missing_required_key' => 'The record is missing a required field.',
            'invalid_slug' => 'The slug is invalid.',
            'invalid_name' => 'The name is invalid.',
            'invalid_source_type' => 'The source type must be rss, atom, html, or manual.',
            'invalid_feed_url' => 'The feed URL is missing, malformed, or unsupported.',
            'invalid_base_url' => 'The base URL is malformed or unsupported.',
            'invalid_allowed_domains' => 'allowed_domains must be a non-empty JSON array of hostnames.',
            'host_not_allowed' => 'The feed URL host, and base URL host when present, must exactly match an allowed domain.',
            'invalid_parser_profile' => 'The parser profile is invalid.',
        );

        return isset($messages[$code]) ? $messages[$code] : 'This record failed validation.';
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
            'too_many_records' => 'The JSON array must contain no more than 80 records.',
            'has_invalid_rows' => 'One or more records are invalid. Nothing will be created until every record is valid or a skippable duplicate.',
            'ok' => '',
        );

        return isset($messages[$code]) ? $messages[$code] : 'The submitted JSON payload is invalid.';
    }

    private function mapPreviewErrorMessage($code)
    {
        $messages = array(
            'permission' => 'You do not have permission to perform that action.',
            'nonce' => 'Security verification failed. Please try again.',
            'invalid_payload' => 'The submitted JSON payload is invalid.',
        );

        return isset($messages[$code]) ? $messages[$code] : '';
    }

    private function mapNoticeMessage($code, $inserted, $duplicate, $invalid)
    {
        $insertedInt = (int) $inserted;
        $duplicateInt = (int) $duplicate;
        $invalidInt = (int) $invalid;

        switch ($code) {
            case 'confirmed':
                return sprintf(
                    'Bulk source onboarding complete. Created: %d. Skipped duplicates: %d.',
                    $insertedInt,
                    $duplicateInt
                );
            case 'insert_failed':
                return sprintf(
                    'Bulk onboarding stopped after an unexpected error. Created before stopping: %d. Skipped duplicates: %d.',
                    $insertedInt,
                    $duplicateInt
                );
            case 'validation_failed':
                return sprintf(
                    'The submitted data failed validation. Nothing was created. Invalid records: %d.',
                    $invalidInt
                );
            case 'permission':
                return 'You do not have permission to perform that action.';
            case 'nonce':
                return 'Security verification failed. Please try again.';
            default:
                return '';
        }
    }
}
