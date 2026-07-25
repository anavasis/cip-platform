<?php

namespace StudyMentor\ContentEngine\Admin\Pages;

use StudyMentor\ContentEngine\Admin\BulkConnectivityAuditService;
use StudyMentor\ContentEngine\Core\FeatureFlags;
use StudyMentor\ContentEngine\Data\SourceRepository;

defined('ABSPATH') || exit;

final class ConnectivityAuditPage
{
    private const MAX_SELECTED_SOURCES = 3;

    private $featureFlags;
    private $sourceRepository;
    private $auditService;
    private $viewPath;

    public function __construct(
        FeatureFlags $featureFlags,
        SourceRepository $sourceRepository,
        BulkConnectivityAuditService $auditService,
        $viewPath
    ) {
        $this->featureFlags = $featureFlags;
        $this->sourceRepository = $sourceRepository;
        $this->auditService = $auditService;
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

        $sources = $this->sourceRepository->findAll();
        $selectedIds = array();
        $requestError = '';
        $auditResults = null;
        $isPost = isset($_SERVER['REQUEST_METHOD'])
            && $_SERVER['REQUEST_METHOD'] === 'POST';

        if ($isPost) {
            $hasMarker = isset($_POST['smce_connectivity_audit'])
                && (is_string($_POST['smce_connectivity_audit'])
                    || is_int($_POST['smce_connectivity_audit']))
                && (string) $_POST['smce_connectivity_audit'] === '1';

            if (!$hasMarker) {
                $requestError = 'invalid_request';
            } elseif (!current_user_can('manage_options')) {
                $requestError = 'permission';
            } elseif (!$this->featureFlags->isEnabled('source_registry')) {
                $requestError = 'permission';
            } elseif (
                !isset($_POST['smce_connectivity_audit_nonce'])
                || !is_string($_POST['smce_connectivity_audit_nonce'])
                || !wp_verify_nonce(
                    sanitize_text_field(
                        wp_unslash($_POST['smce_connectivity_audit_nonce'])
                    ),
                    'smce_connectivity_audit'
                )
            ) {
                $requestError = 'nonce';
            } else {
                $validation = $this->validateSourceIds(
                    isset($_POST['source_ids']) ? $_POST['source_ids'] : null,
                    array_key_exists('source_ids', $_POST)
                );

                if ($validation['valid'] !== true) {
                    $requestError = 'invalid_source_ids';
                } else {
                    $selectedIds = $validation['source_ids'];
                    $auditResults = $this->auditService->audit($selectedIds);
                }
            }
        }

        $data = array(
            'title' => 'Connectivity Audit',
            'page_url' => admin_url('admin.php?page=smce-connectivity-audit'),
            'nonce_field' => 'smce_connectivity_audit_nonce',
            'nonce_action' => 'smce_connectivity_audit',
            'maximum_sources' => self::MAX_SELECTED_SOURCES,
            'sources' => $this->buildSourceRows($sources, $selectedIds),
            'request_error_message' => $this->requestErrorMessage($requestError),
            'results' => $this->buildResultRows($auditResults),
        );

        require $this->viewPath;
    }

    /**
     * @return array{valid: bool, source_ids: array<int, int>}
     */
    private function validateSourceIds($rawSourceIds, $fieldExists)
    {
        if (!$fieldExists || !is_array($rawSourceIds)) {
            return array('valid' => false, 'source_ids' => array());
        }

        $rawCount = count($rawSourceIds);

        if ($rawCount < 1 || $rawCount > self::MAX_SELECTED_SOURCES) {
            return array('valid' => false, 'source_ids' => array());
        }

        $maximumInteger = (string) PHP_INT_MAX;
        $deduplicated = array();

        foreach ($rawSourceIds as $rawSourceId) {
            if (!is_string($rawSourceId) && !is_int($rawSourceId)) {
                return array('valid' => false, 'source_ids' => array());
            }

            $canonical = (string) $rawSourceId;

            if (preg_match('/^[1-9][0-9]*$/', $canonical) !== 1) {
                return array('valid' => false, 'source_ids' => array());
            }

            if (
                strlen($canonical) > strlen($maximumInteger)
                || (
                    strlen($canonical) === strlen($maximumInteger)
                    && strcmp($canonical, $maximumInteger) > 0
                )
            ) {
                return array('valid' => false, 'source_ids' => array());
            }

            if (!isset($deduplicated[$canonical])) {
                $deduplicated[$canonical] = (int) $canonical;
            }
        }

        $sourceIds = array_values($deduplicated);

        if (
            count($sourceIds) < 1
            || count($sourceIds) > self::MAX_SELECTED_SOURCES
        ) {
            return array('valid' => false, 'source_ids' => array());
        }

        return array('valid' => true, 'source_ids' => $sourceIds);
    }

    /**
     * @param mixed $sources
     * @param array<int, int> $selectedIds
     * @return array<int, array<string, mixed>>
     */
    private function buildSourceRows($sources, array $selectedIds)
    {
        if (!is_array($sources)) {
            return array();
        }

        $rows = array();

        foreach ($sources as $source) {
            if (!is_array($source) || !isset($source['id'])) {
                continue;
            }

            $sourceId = (int) $source['id'];

            if ($sourceId <= 0) {
                continue;
            }

            $feedUrl = isset($source['feed_url']) && is_string($source['feed_url'])
                ? $source['feed_url']
                : '';
            $host = wp_parse_url($feedUrl, PHP_URL_HOST);

            $rows[] = array(
                'id' => $sourceId,
                'name' => $this->boundedText(
                    isset($source['name']) ? $source['name'] : '',
                    200
                ),
                'source_type' => $this->boundedText(
                    isset($source['source_type']) ? $source['source_type'] : '',
                    30
                ),
                'host' => $this->buildSafeDisplayHost($host),
                'status' => isset($source['enabled']) && (int) $source['enabled'] === 1
                    ? 'Enabled'
                    : 'Disabled',
                'manual_only' => isset($source['manual_only'])
                    && (int) $source['manual_only'] === 1
                    ? 'Yes'
                    : 'No',
                'selected' => in_array($sourceId, $selectedIds, true),
            );
        }

        return $rows;
    }

    /**
     * @param mixed $auditResults
     * @return array<int, array<string, mixed>>|null
     */
    private function buildResultRows($auditResults)
    {
        if ($auditResults === null) {
            return null;
        }

        if (!is_array($auditResults)) {
            return array();
        }

        $rows = array();

        foreach ($auditResults as $result) {
            if (!is_array($result)) {
                continue;
            }

            $resultCode = isset($result['result_code'])
                ? (string) $result['result_code']
                : 'network_error';

            $rows[] = array(
                'source_id' => isset($result['source_id']) ? (int) $result['source_id'] : 0,
                'name' => $this->boundedText(isset($result['name']) ? $result['name'] : '', 200),
                'host' => $this->buildSafeDisplayHost(
                    isset($result['host']) ? $result['host'] : ''
                ),
                'result_label' => $this->resultLabel($resultCode),
                'http_status' => isset($result['http_status'])
                    ? max(0, min(999, (int) $result['http_status']))
                    : 0,
                'content_type' => $this->boundedText(
                    isset($result['content_type']) ? $result['content_type'] : '',
                    100
                ),
                'response_bytes' => isset($result['response_bytes'])
                    ? max(0, min(131073, (int) $result['response_bytes']))
                    : 0,
                'elapsed_ms' => isset($result['elapsed_ms'])
                    ? max(0, min(2147483647, (int) $result['elapsed_ms']))
                    : 0,
                'redirect_count' => isset($result['redirect_count'])
                    ? max(0, min(2, (int) $result['redirect_count']))
                    : 0,
                'truncated' => isset($result['truncated'])
                    && $result['truncated'] === true,
            );
        }

        return $rows;
    }

    private function resultLabel($code)
    {
        $labels = array(
            'reachable_rss' => 'Reachable RSS',
            'reachable_atom' => 'Reachable Atom',
            'reachable_html' => 'Reachable HTML',
            'reachable_other' => 'Reachable other content',
            'unauthorized_401' => 'Unauthorized (401)',
            'forbidden_403' => 'Forbidden (403)',
            'not_found_404' => 'Not found (404)',
            'rate_limited_429' => 'Rate limited (429)',
            'server_error' => 'Server error',
            'http_error' => 'HTTP error',
            'timeout' => 'Timeout',
            'dns_failure' => 'DNS failure',
            'tls_failure' => 'TLS failure',
            'unsafe_url' => 'Unsafe URL',
            'unsafe_redirect' => 'Unsafe redirect',
            'redirect_domain_blocked' => 'Redirect domain blocked',
            'redirect_limit_exceeded' => 'Redirect limit exceeded',
            'response_too_large' => 'Response too large',
            'invalid_source_configuration' => 'Invalid source configuration',
            'network_error' => 'Network error',
        );

        return isset($labels[$code]) ? $labels[$code] : $labels['network_error'];
    }

    private function requestErrorMessage($code)
    {
        $messages = array(
            'invalid_request' => 'The submitted audit request is invalid.',
            'permission' => 'You do not have permission to perform that action.',
            'nonce' => 'Security verification failed. Please try again.',
            'invalid_source_ids' => 'Select between one and three valid source IDs.',
        );

        return isset($messages[$code]) ? $messages[$code] : '';
    }

    private function buildSafeDisplayHost($host): string
    {
        if (!is_scalar($host)) {
            return '';
        }

        $normalized = strtolower(rtrim(trim((string) $host), '.'));

        if ($normalized === '') {
            return '';
        }

        $normalized = $this->boundedText($normalized, 253);
        $ipCandidate = $normalized;

        if (
            strlen($ipCandidate) >= 2
            && $ipCandidate[0] === '['
            && substr($ipCandidate, -1) === ']'
        ) {
            $ipCandidate = substr($ipCandidate, 1, -1);
        }

        if (filter_var($ipCandidate, FILTER_VALIDATE_IP) !== false) {
            return '';
        }

        if (
            filter_var(
                $normalized,
                FILTER_VALIDATE_DOMAIN,
                FILTER_FLAG_HOSTNAME
            ) === false
        ) {
            return '';
        }

        return $normalized;
    }

    private function boundedText($value, $maximumCharacters)
    {
        $text = sanitize_text_field(is_scalar($value) ? (string) $value : '');

        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, $maximumCharacters, 'UTF-8');
        }

        return substr($text, 0, $maximumCharacters);
    }
}
