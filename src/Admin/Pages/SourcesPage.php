<?php

namespace StudyMentor\ContentEngine\Admin\Pages;

use StudyMentor\ContentEngine\Admin\SourceCheckService;
use StudyMentor\ContentEngine\Core\FeatureFlags;
use StudyMentor\ContentEngine\Data\SourceRegistryService;
use StudyMentor\ContentEngine\Data\SourceRepository;

defined('ABSPATH') || exit;

final class SourcesPage
{
    private $featureFlags;
    private $repository;
    private $registryService;
    private $sourceCheckService;

    public function __construct(
        FeatureFlags $featureFlags,
        SourceRepository $repository,
        SourceRegistryService $registryService,
        SourceCheckService $sourceCheckService
    ) {
        $this->featureFlags = $featureFlags;
        $this->repository = $repository;
        $this->registryService = $registryService;
        $this->sourceCheckService = $sourceCheckService;
    }

    public function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html('You do not have permission to view this page.'));
        }

        if (!$this->featureFlags->isEnabled('source_registry')) {
            wp_die(esc_html('The Sources Registry is not enabled.'));
        }

        $editSource = null;
        $editId = 0;
        $checkResult = null;

        if (isset($_GET['action'], $_GET['id']) && sanitize_key(wp_unslash((string) $_GET['action'])) === 'edit') {
            $editId = (int) $_GET['id'];
        } elseif (
            isset($_SERVER['REQUEST_METHOD'])
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['smce_source_check'], $_POST['source_id'])
        ) {
            $editId = (int) $_POST['source_id'];
        }

        if ($editId > 0) {
            $editSource = $this->repository->findById($editId);
        }

        if (
            isset($_SERVER['REQUEST_METHOD'])
            && $_SERVER['REQUEST_METHOD'] === 'POST'
            && isset($_POST['smce_source_check'])
        ) {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html('You do not have permission to perform that action.'));
            }

            $sourceId = isset($_POST['source_id']) ? (int) $_POST['source_id'] : 0;

            if ($sourceId <= 0) {
                $checkResult = array(
                    'success' => false,
                    'error_code' => 'invalid_id',
                    'error_message' => 'The requested source identifier is invalid.',
                    'requested_url' => '',
                    'final_url' => '',
                    'http_status' => 0,
                    'content_type' => '',
                    'response_size' => 0,
                    'format' => '',
                    'item_count' => 0,
                    'preview_items' => array(),
                );
            } elseif (
                !isset($_POST['smce_source_nonce'])
                || !wp_verify_nonce(
                    sanitize_text_field(wp_unslash((string) $_POST['smce_source_nonce'])),
                    'smce_source_check_' . $sourceId
                )
            ) {
                $checkResult = array(
                    'success' => false,
                    'error_code' => 'nonce',
                    'error_message' => 'Security verification failed. Please try again.',
                    'requested_url' => '',
                    'final_url' => '',
                    'http_status' => 0,
                    'content_type' => '',
                    'response_size' => 0,
                    'format' => '',
                    'item_count' => 0,
                    'preview_items' => array(),
                );
            } else {
                $checkResult = $this->sourceCheckService->check($sourceId);
            }

            if ($editSource === null && $sourceId > 0) {
                $editSource = $this->repository->findById($sourceId);
            }
        }

        $sources = $this->repository->findAll();
        $listRows = array();

        foreach ($sources as $source) {
            if (!is_array($source)) {
                continue;
            }

            $listRows[] = array(
                'id' => (int) $source['id'],
                'slug' => (string) $source['slug'],
                'name' => (string) $source['name'],
                'source_type' => (string) $source['source_type'],
                'feed_url' => (string) $source['feed_url'],
                'enabled' => (int) $source['enabled'] === 1,
                'edit_url' => admin_url('admin.php?page=smce-sources&action=edit&id=' . (int) $source['id']),
            );
        }

        $editForm = null;

        if ($editSource !== null) {
            $editForm = array(
                'id' => (int) $editSource['id'],
                'slug' => (string) $editSource['slug'],
                'name' => (string) $editSource['name'],
                'source_type' => (string) $editSource['source_type'],
                'base_url' => $editSource['base_url'] !== null ? (string) $editSource['base_url'] : '',
                'feed_url' => (string) $editSource['feed_url'],
                'allowed_domains' => $this->registryService->decodeAllowedDomainsForDisplay(
                    (string) $editSource['allowed_domains']
                ),
                'parser_profile' => $editSource['parser_profile'] !== null
                    ? (string) $editSource['parser_profile']
                    : '',
                'enabled' => (int) $editSource['enabled'] === 1,
                'update_action' => admin_url('admin-post.php'),
                'nonce_action' => 'smce_source_update_' . (int) $editSource['id'],
                'toggle_action' => admin_url('admin-post.php'),
                'toggle_nonce_action' => 'smce_source_toggle_' . (int) $editSource['id'],
                'check_action' => admin_url(
                    'admin.php?page=smce-sources&action=edit&id=' . (int) $editSource['id']
                ),
                'check_nonce_action' => 'smce_source_check_' . (int) $editSource['id'],
            );
        }

        $noticeCode = '';
        $errorCode = '';

        if (isset($_GET['smce_notice'])) {
            $noticeCode = sanitize_key(wp_unslash((string) $_GET['smce_notice']));
        }

        if (isset($_GET['smce_error'])) {
            $errorCode = sanitize_key(wp_unslash((string) $_GET['smce_error']));
        }

        $data = array(
            'title' => 'Sources',
            'notice_message' => $this->mapNoticeMessage($noticeCode),
            'error_message' => $this->mapErrorMessage($errorCode),
            'sources' => $listRows,
            'edit_source' => $editForm,
            'check_result' => $checkResult,
            'create_action' => admin_url('admin-post.php'),
            'create_nonce_action' => 'smce_source_create',
            'source_types' => array('rss', 'atom', 'html', 'manual'),
            'list_url' => admin_url('admin.php?page=smce-sources'),
        );

        require SMCE_PLUGIN_DIR . 'views/admin/sources.php';
    }

    private function mapNoticeMessage($code)
    {
        $messages = array(
            'created' => 'Source created successfully. It remains disabled until enabled separately.',
            'updated' => 'Source updated successfully.',
            'enabled' => 'Source enabled successfully.',
            'disabled' => 'Source disabled successfully.',
        );

        return isset($messages[$code]) ? $messages[$code] : '';
    }

    private function mapErrorMessage($code)
    {
        $messages = array(
            'permission' => 'You do not have permission to perform that action.',
            'nonce' => 'Security verification failed. Please try again.',
            'invalid_id' => 'The requested source identifier is invalid.',
            'not_found' => 'The requested source could not be found.',
            'validation' => 'One or more submitted values failed validation.',
            'invalid_slug' => 'The slug is required and must use lowercase letters, numbers, and hyphens only.',
            'duplicate_slug' => 'That slug is already in use.',
            'invalid_name' => 'The name is required and must be 191 characters or fewer.',
            'invalid_source_type' => 'The source type must be one of: rss, atom, html, manual.',
            'invalid_base_url' => 'The base URL must be a valid http or https URL without credentials.',
            'invalid_feed_url' => 'The feed URL is required and must be a valid http or https URL without credentials.',
            'duplicate_feed_url' => 'That feed URL is already registered.',
            'invalid_domain' => 'One or more allowed domains are invalid.',
            'database' => 'The source could not be saved. Please try again.',
        );

        return isset($messages[$code]) ? $messages[$code] : '';
    }
}
