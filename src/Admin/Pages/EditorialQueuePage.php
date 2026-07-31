<?php

namespace StudyMentor\ContentEngine\Admin\Pages;

use StudyMentor\ContentEngine\Announcement\EditorialWorkspaceQueryService;
use StudyMentor\ContentEngine\Data\SourceItemReadRepository;
use StudyMentor\ContentEngine\Platform\PlatformDiagnostics;

defined('ABSPATH') || exit;

final class EditorialQueuePage
{
    private const MAX_PAGE = 200;

    private $repository;
    private $queryService;
    private $platformDiagnostics;
    private $viewPath;

    public function __construct(
        SourceItemReadRepository $repository,
        EditorialWorkspaceQueryService $queryService,
        PlatformDiagnostics $platformDiagnostics,
        $viewPath
    ) {
        $this->repository = $repository;
        $this->queryService = $queryService;
        $this->platformDiagnostics = $platformDiagnostics;
        $this->viewPath = $viewPath;
    }

    public function render()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html('You do not have permission to view this page.'));
        }

        $data = $this->emptyViewData();
        $requestMethod = isset($_SERVER['REQUEST_METHOD'])
            && is_string($_SERVER['REQUEST_METHOD'])
            ? strtoupper($_SERVER['REQUEST_METHOD'])
            : '';

        if ($requestMethod !== 'GET') {
            $data['error_messages'][] = 'This read-only page accepts GET requests only.';
            require $this->viewPath;
            return;
        }

        $validationErrors = array();
        $statusValue = $this->readGetString('status', $validationErrors);
        $status = 'new';

        if ($statusValue !== null && $statusValue !== '') {
            if (!in_array($statusValue, array('new', 'updated'), true)) {
                $validationErrors[] = 'The queue status is invalid.';
            } else {
                $status = $statusValue;
            }
        }

        $pageValue = $this->readGetString('paged', $validationErrors);
        $page = 1;
        if ($pageValue !== null) {
            $parsedPage = $this->parsePositiveInteger($pageValue);
            if ($parsedPage === null || $parsedPage > self::MAX_PAGE) {
                $validationErrors[] = 'The requested page is invalid.';
            } else {
                $page = $parsedPage;
            }
        }

        $data['active_status'] = $status;
        $data['workspace_url'] = add_query_arg(
            array('page' => 'smce-editorial'),
            admin_url('admin.php')
        );
        $data['new_url'] = $this->buildAdminUrl(array('status' => 'new'));
        $data['updated_url'] = $this->buildAdminUrl(array('status' => 'updated'));
        $data['announcements_url'] = add_query_arg(
            array('page' => 'smce-editorial-announcements'),
            admin_url('admin.php')
        );

        $platform = $this->platformDiagnostics->collect();
        $data['spine_ready'] = isset($platform['confirmations']['announcement_lifecycle'])
            ? (string) $platform['confirmations']['announcement_lifecycle']
            : 'Not ready';
        $data['last_batch'] = isset($platform['announcement_lifecycle']['last_batch'])
            && is_array($platform['announcement_lifecycle']['last_batch'])
            ? $platform['announcement_lifecycle']['last_batch']
            : null;

        if ($validationErrors !== array()) {
            $data['error_messages'] = array_values(array_unique($validationErrors));
            require $this->viewPath;
            return;
        }

        $criteria = array(
            'search' => '',
            'source_id' => null,
            'status' => $status,
            'date_from' => null,
            'date_to' => null,
            'sort' => 'updated',
            'direction' => 'desc',
            'page' => $page,
        );

        $result = $this->repository->findPage($criteria);

        if ($result['ok'] !== true) {
            $data['error_messages'][] = 'Editorial queue could not be loaded. Please try again.';
            require $this->viewPath;
            return;
        }

        foreach ($result['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $revisionNo = isset($item['revision_no']) ? (int) $item['revision_no'] : 0;
            $itemId = isset($item['id']) ? (int) $item['id'] : 0;
            $data['items'][] = array(
                'id' => $itemId,
                'source_id' => isset($item['source_id']) ? (int) $item['source_id'] : 0,
                'source_name' => $this->displayValue(
                    isset($item['source_name']) ? $item['source_name'] : null
                ),
                'title' => $this->displayValue(isset($item['raw_title']) ? $item['raw_title'] : null),
                'status' => $this->queryService->statusFromRevision($revisionNo),
                'revision_no' => $this->displayValue(
                    isset($item['revision_no']) ? $item['revision_no'] : null
                ),
                'updated_at_utc' => $this->displayValue(
                    isset($item['updated_at_utc']) ? $item['updated_at_utc'] : null
                ),
                'details_url' => add_query_arg(
                    array(
                        'page' => 'smce-editorial-announcements',
                        'item_id' => $itemId,
                    ),
                    admin_url('admin.php')
                ),
            );
        }

        $data['page'] = (int) $result['page'];
        $data['has_previous'] = $result['has_previous'] === true;
        $data['has_next'] = $result['has_next'] === true;
        $data['limit_reached'] = $result['limit_reached'] === true;

        if ($data['has_previous']) {
            $data['previous_url'] = $this->buildAdminUrl(array(
                'status' => $status,
                'paged' => $data['page'] - 1,
            ));
        }

        if ($data['has_next']) {
            $data['next_url'] = $this->buildAdminUrl(array(
                'status' => $status,
                'paged' => $data['page'] + 1,
            ));
        }

        require $this->viewPath;
    }

    private function readGetString($name, array &$errors)
    {
        if (!array_key_exists($name, $_GET)) {
            return null;
        }

        if (!is_scalar($_GET[$name])) {
            $errors[] = 'One or more filter values are invalid.';
            return null;
        }

        return wp_unslash((string) $_GET[$name]);
    }

    private function parsePositiveInteger($value)
    {
        if (!is_string($value) || preg_match('/^[0-9]+$/D', $value) !== 1) {
            return null;
        }

        $normalized = ltrim($value, '0');
        if ($normalized === '') {
            return null;
        }

        return (int) $normalized;
    }

    private function displayValue($value)
    {
        if (!is_scalar($value)) {
            return '—';
        }

        $display = trim((string) $value);
        return $display === '' ? '—' : $display;
    }

    private function buildAdminUrl(array $queryArgs)
    {
        $args = array_merge(array('page' => 'smce-editorial-queue'), $queryArgs);
        return add_query_arg($args, admin_url('admin.php'));
    }

    private function emptyViewData()
    {
        return array(
            'title' => 'Editorial Queue',
            'error_messages' => array(),
            'active_status' => 'new',
            'items' => array(),
            'page' => 1,
            'has_previous' => false,
            'has_next' => false,
            'limit_reached' => false,
            'previous_url' => '',
            'next_url' => '',
            'workspace_url' => '',
            'new_url' => '',
            'updated_url' => '',
            'announcements_url' => '',
            'spine_ready' => 'Not ready',
            'last_batch' => null,
        );
    }
}
