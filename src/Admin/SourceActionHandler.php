<?php

namespace StudyMentor\ContentEngine\Admin;

use StudyMentor\ContentEngine\Core\FeatureFlags;
use StudyMentor\ContentEngine\Data\SourceRegistryService;

defined('ABSPATH') || exit;

final class SourceActionHandler
{
    private $featureFlags;
    private $registryService;

    public function __construct(FeatureFlags $featureFlags, SourceRegistryService $registryService)
    {
        $this->featureFlags = $featureFlags;
        $this->registryService = $registryService;
    }

    public function register()
    {
        if (!$this->featureFlags->isEnabled('source_registry')) {
            return;
        }

        add_action('admin_post_smce_source_create', array($this, 'handleCreate'));
        add_action('admin_post_smce_source_update', array($this, 'handleUpdate'));
        add_action('admin_post_smce_source_toggle', array($this, 'handleToggle'));
    }

    public function handleCreate()
    {
        if (!$this->featureFlags->isEnabled('source_registry')) {
            $this->redirectWithError('permission');
        }

        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            $this->redirectWithError('permission');
        }

        if (
            !isset($_POST['smce_source_nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash((string) $_POST['smce_source_nonce'])),
                'smce_source_create'
            )
        ) {
            $this->redirectWithError('nonce');
        }

        $input = array(
            'slug' => isset($_POST['slug']) ? $_POST['slug'] : '',
            'name' => isset($_POST['name']) ? $_POST['name'] : '',
            'source_type' => isset($_POST['source_type']) ? $_POST['source_type'] : '',
            'base_url' => isset($_POST['base_url']) ? $_POST['base_url'] : '',
            'feed_url' => isset($_POST['feed_url']) ? $_POST['feed_url'] : '',
            'allowed_domains' => isset($_POST['allowed_domains']) ? $_POST['allowed_domains'] : '',
            'parser_profile' => isset($_POST['parser_profile']) ? $_POST['parser_profile'] : '',
        );

        $result = $this->registryService->create($input);

        if (empty($result['success'])) {
            $this->redirectWithError(isset($result['error']) ? (string) $result['error'] : 'validation');
        }

        $this->redirectWithNotice('created');
    }

    public function handleUpdate()
    {
        if (!$this->featureFlags->isEnabled('source_registry')) {
            $this->redirectWithError('permission');
        }

        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            $this->redirectWithError('permission');
        }

        $sourceId = isset($_POST['source_id']) ? (int) $_POST['source_id'] : 0;

        if ($sourceId <= 0) {
            $this->redirectWithError('invalid_id');
        }

        $nonceAction = 'smce_source_update_' . $sourceId;

        if (
            !isset($_POST['smce_source_nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash((string) $_POST['smce_source_nonce'])),
                $nonceAction
            )
        ) {
            $this->redirectWithError('nonce');
        }

        $input = array(
            'name' => isset($_POST['name']) ? $_POST['name'] : '',
            'source_type' => isset($_POST['source_type']) ? $_POST['source_type'] : '',
            'base_url' => isset($_POST['base_url']) ? $_POST['base_url'] : '',
            'feed_url' => isset($_POST['feed_url']) ? $_POST['feed_url'] : '',
            'allowed_domains' => isset($_POST['allowed_domains']) ? $_POST['allowed_domains'] : '',
            'parser_profile' => isset($_POST['parser_profile']) ? $_POST['parser_profile'] : '',
        );

        $result = $this->registryService->update($sourceId, $input);

        if (empty($result['success'])) {
            $error = isset($result['error']) ? (string) $result['error'] : 'validation';
            $this->redirectWithError($error, array('action' => 'edit', 'id' => $sourceId));
        }

        $this->redirectWithNotice('updated', array('action' => 'edit', 'id' => $sourceId));
    }

    public function handleToggle()
    {
        if (!$this->featureFlags->isEnabled('source_registry')) {
            $this->redirectWithError('permission');
        }

        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            $this->redirectWithError('permission');
        }

        $sourceId = isset($_POST['source_id']) ? (int) $_POST['source_id'] : 0;
        $enabled = isset($_POST['enabled']) ? (int) $_POST['enabled'] : -1;

        if ($sourceId <= 0) {
            $this->redirectWithError('invalid_id');
        }

        $nonceAction = 'smce_source_toggle_' . $sourceId;

        if (
            !isset($_POST['smce_source_nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash((string) $_POST['smce_source_nonce'])),
                $nonceAction
            )
        ) {
            $this->redirectWithError('nonce');
        }

        $result = $this->registryService->toggle($sourceId, $enabled);

        if (empty($result['success'])) {
            $error = isset($result['error']) ? (string) $result['error'] : 'validation';
            $this->redirectWithError($error);
        }

        $notice = !empty($result['enabled']) ? 'enabled' : 'disabled';
        $this->redirectWithNotice($notice);
    }

    private function redirectWithNotice($code, array $extraArgs = array())
    {
        $this->redirect($code, '', $extraArgs);
    }

    private function redirectWithError($code, array $extraArgs = array())
    {
        $this->redirect('', $code, $extraArgs);
    }

    private function redirect($noticeCode, $errorCode, array $extraArgs = array())
    {
        $args = array('page' => 'smce-sources');

        foreach ($extraArgs as $key => $value) {
            if ($key === 'id') {
                $args['id'] = (int) $value;
                continue;
            }

            if ($key === 'action') {
                $args['action'] = sanitize_key((string) $value);
            }
        }

        if ($noticeCode !== '') {
            $args['smce_notice'] = sanitize_key($noticeCode);
        }

        if ($errorCode !== '') {
            $args['smce_error'] = sanitize_key($errorCode);
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }
}
