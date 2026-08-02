<?php

namespace StudyMentor\ContentEngine\Admin\Pages;

use StudyMentor\ContentEngine\Announcement\EditorialWorkspaceQueryService;
use StudyMentor\ContentEngine\Article\ArticlePreviewRepositoryInterface;
use StudyMentor\ContentEngine\Data\SourceItemReadRepository;
use StudyMentor\ContentEngine\Generation\GenerationOrchestrator;
use StudyMentor\ContentEngine\Platform\PlatformDiagnostics;

defined('ABSPATH') || exit;

final class EditorialAnnouncementsPage
{
    private const MAX_SEARCH_LENGTH = 100;
    private const MAX_PAGE = 200;

    private $repository;
    private $queryService;
    private $platformDiagnostics;
    private $orchestrator;
    private $previewRepository;
    private $viewPath;

    public function __construct(
        SourceItemReadRepository $repository,
        EditorialWorkspaceQueryService $queryService,
        PlatformDiagnostics $platformDiagnostics,
        GenerationOrchestrator $orchestrator,
        ArticlePreviewRepositoryInterface $previewRepository,
        $viewPath
    ) {
        $this->repository = $repository;
        $this->queryService = $queryService;
        $this->platformDiagnostics = $platformDiagnostics;
        $this->orchestrator = $orchestrator;
        $this->previewRepository = $previewRepository;
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

        $isGeneratePost = $requestMethod === 'POST'
            && isset($_POST['smce_editorial_generate']);

        if ($requestMethod !== 'GET' && !$isGeneratePost) {
            $data['error_messages'][] = 'This page accepts GET requests, or POST for Generate only.';
            require $this->viewPath;
            return;
        }

        $validationErrors = array();
        $criteria = $isGeneratePost
            ? $this->parseListCriteriaFromPost($validationErrors)
            : $this->parseListCriteria($validationErrors);
        $hasItemId = $isGeneratePost
            ? array_key_exists('item_id', $_POST)
            : array_key_exists('item_id', $_GET);
        $itemId = null;

        if ($hasItemId) {
            $itemIdValue = $isGeneratePost
                ? $this->readPostString('item_id', $validationErrors)
                : $this->readGetString('item_id', $validationErrors);
            $itemId = $this->parsePositiveInteger($itemIdValue);

            if ($itemId === null) {
                $validationErrors[] = 'The announcement identifier is invalid.';
            }
        }

        if ($isGeneratePost && $itemId !== null) {
            $generateOutcome = $this->handleGeneratePost($itemId);
            if ($generateOutcome['ok'] !== true) {
                $validationErrors[] = $generateOutcome['message'];
            } else {
                $data['success_messages'][] = $generateOutcome['message'];
                $data['article_preview'] = $generateOutcome['preview'];
                $data['generation_result'] = $generateOutcome['meta'];
            }
        }

        $data['filters'] = $this->buildFilterDisplay($criteria);
        $data['reset_url'] = $this->buildAdminUrl(array());
        $data['back_url'] = $this->buildAdminUrl(
            $this->buildListQueryArgs($criteria, true)
        );
        $data['workspace_url'] = add_query_arg(
            array('page' => 'smce-editorial'),
            admin_url('admin.php')
        );

        if ($validationErrors !== array() && !$hasItemId) {
            $data['error_messages'] = array_values(array_unique($validationErrors));
            $this->loadSourceOptions($data);
            require $this->viewPath;
            return;
        }

        if ($hasItemId) {
            $data['mode'] = 'detail';
            if ($itemId === null) {
                $data['error_messages'] = array_values(array_unique($validationErrors));
                require $this->viewPath;
                return;
            }

            $result = $this->repository->findById($itemId);

            if ($result['ok'] !== true) {
                $data['error_messages'][] = 'The announcement could not be loaded. Please try again.';
            } elseif ($result['found'] !== true || !is_array($result['item'])) {
                $data['error_messages'][] = 'The requested announcement was not found.';
            } else {
                if ($validationErrors !== array()) {
                    $data['error_messages'] = array_merge(
                        $data['error_messages'],
                        array_values(array_unique($validationErrors))
                    );
                }

                $data['detail_item'] = $this->buildDetailItem($result['item']);
                $data['generate_form_url'] = $this->buildAdminUrl(
                    array_merge(
                        $this->buildListQueryArgs($criteria, true),
                        array('item_id' => $itemId)
                    )
                );
                $data['generate_nonce_action'] = 'smce_editorial_generate_' . $itemId;
                $platform = $this->platformDiagnostics->collect();
                $data['lifecycle_diagnostics'] = isset($platform['announcement_lifecycle'])
                    && is_array($platform['announcement_lifecycle'])
                    ? $platform['announcement_lifecycle']
                    : array(
                        'status' => 'not_bound',
                        'store' => 'smce_source_items',
                        'last_batch' => null,
                    );
                $data['spine_ready'] = isset($platform['confirmations']['announcement_lifecycle'])
                    ? (string) $platform['confirmations']['announcement_lifecycle']
                    : 'Not ready';
                $data['last_generation'] = isset($platform['last_generation'])
                    && is_array($platform['last_generation'])
                    ? $platform['last_generation']
                    : null;

                if ($data['article_preview'] === null) {
                    $existing = $this->previewRepository->findLatestForAnnouncement($itemId);
                    if ($existing !== null) {
                        $data['article_preview'] = $existing->toArray();
                    }
                }
            }

            require $this->viewPath;
            return;
        }

        $this->loadSourceOptions($data);
        $result = $this->repository->findPage($criteria);

        if ($result['ok'] !== true) {
            $data['error_messages'][] = 'Announcements could not be loaded. Please try again.';
            require $this->viewPath;
            return;
        }

        $detailQueryArgs = $this->buildListQueryArgs($criteria, true);
        foreach ($result['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $displayItem = $this->buildListItem($item);
            $itemQueryArgs = $detailQueryArgs;
            $itemQueryArgs['item_id'] = $displayItem['id'];
            $displayItem['details_url'] = $this->buildAdminUrl($itemQueryArgs);
            $data['items'][] = $displayItem;
        }

        $data['page'] = (int) $result['page'];
        $data['has_previous'] = $result['has_previous'] === true;
        $data['has_next'] = $result['has_next'] === true;
        $data['limit_reached'] = $result['limit_reached'] === true;

        $paginationArgs = $this->buildListQueryArgs($criteria, false);
        if ($data['has_previous']) {
            $previousArgs = $paginationArgs;
            $previousArgs['paged'] = $data['page'] - 1;
            $data['previous_url'] = $this->buildAdminUrl($previousArgs);
        }

        if ($data['has_next']) {
            $nextArgs = $paginationArgs;
            $nextArgs['paged'] = $data['page'] + 1;
            $data['next_url'] = $this->buildAdminUrl($nextArgs);
        }

        require $this->viewPath;
    }

    /**
     * @param int $itemId
     * @return array{ok:bool,message:string,preview:?array<string,mixed>,meta:?array<string,mixed>}
     */
    private function handleGeneratePost($itemId)
    {
        if (!current_user_can('manage_options')) {
            return array(
                'ok' => false,
                'message' => 'You do not have permission to generate a preview.',
                'preview' => null,
                'meta' => null,
            );
        }

        if (
            !isset($_POST['smce_editorial_generate_nonce'])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash((string) $_POST['smce_editorial_generate_nonce'])),
                'smce_editorial_generate_' . (int) $itemId
            )
        ) {
            return array(
                'ok' => false,
                'message' => 'Security verification failed. Please try again.',
                'preview' => null,
                'meta' => null,
            );
        }

        $loaded = $this->repository->findById((int) $itemId);
        if ($loaded['ok'] !== true || $loaded['found'] !== true || !is_array($loaded['item'])) {
            return array(
                'ok' => false,
                'message' => 'The announcement could not be loaded for generation.',
                'preview' => null,
                'meta' => null,
            );
        }

        $outcome = $this->orchestrator->generateFromAnnouncement($loaded['item']);
        if ($outcome['ok'] !== true) {
            return array(
                'ok' => false,
                'message' => isset($outcome['error'])
                    ? 'Generation failed: ' . (string) $outcome['error']
                    : 'Generation failed.',
                'preview' => null,
                'meta' => array(
                    'stages' => isset($outcome['stages']) ? $outcome['stages'] : array(),
                ),
            );
        }

        $preview = isset($outcome['preview']) ? $outcome['preview'] : null;
        $previewArray = $preview !== null && method_exists($preview, 'toArray')
            ? $preview->toArray()
            : null;

        return array(
            'ok' => true,
            'message' => 'Article preview generated with stub provider (not published).',
            'preview' => $previewArray,
            'meta' => array(
                'blueprint_id' => isset($outcome['blueprint_id']) ? (string) $outcome['blueprint_id'] : '',
                'request_id' => isset($outcome['request_id']) ? (string) $outcome['request_id'] : '',
                'result_id' => isset($outcome['result_id']) ? (string) $outcome['result_id'] : '',
                'preview_id' => isset($outcome['preview_id']) ? (string) $outcome['preview_id'] : '',
                'stages' => isset($outcome['stages']) ? $outcome['stages'] : array(),
            ),
        );
    }

    /**
     * @param array<int, string> $errors
     * @return array<string, mixed>
     */
    private function parseListCriteriaFromPost(array &$errors)
    {
        $searchValue = $this->readPostString('s', $errors);
        $search = '';
        if ($searchValue !== null) {
            $search = trim(sanitize_text_field($searchValue));
        }

        $sourceIdValue = $this->readPostString('source_id_filter', $errors);
        $sourceId = null;
        if ($sourceIdValue !== null && $sourceIdValue !== '') {
            $sourceId = $this->parsePositiveInteger($sourceIdValue);
        }

        $statusValue = $this->readPostString('status', $errors);
        $status = null;
        if ($statusValue !== null && $statusValue !== '' && in_array($statusValue, array('new', 'updated'), true)) {
            $status = $statusValue;
        }

        $dateFrom = $this->parseDate($this->readPostString('date_from', $errors));
        $dateTo = $this->parseDate($this->readPostString('date_to', $errors));

        $sortValue = $this->readPostString('sort', $errors);
        $allowedSorts = array('published', 'created', 'title', 'id', 'last_seen', 'updated');
        $sort = $sortValue === null || !in_array($sortValue, $allowedSorts, true)
            ? 'updated'
            : $sortValue;

        $directionValue = $this->readPostString('direction', $errors);
        $direction = $directionValue === null || !in_array($directionValue, array('asc', 'desc'), true)
            ? 'desc'
            : $directionValue;

        $pageValue = $this->readPostString('paged', $errors);
        $page = 1;
        if ($pageValue !== null) {
            $parsedPage = $this->parsePositiveInteger($pageValue);
            if ($parsedPage !== null && $parsedPage <= self::MAX_PAGE) {
                $page = $parsedPage;
            }
        }

        return array(
            'search' => $search,
            'source_id' => $sourceId,
            'status' => $status,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
        );
    }

    private function readPostString($name, array &$errors)
    {
        if (!array_key_exists($name, $_POST)) {
            return null;
        }

        if (!is_scalar($_POST[$name])) {
            $errors[] = 'One or more values are invalid.';
            return null;
        }

        return wp_unslash((string) $_POST[$name]);
    }

    private function parseListCriteria(array &$errors)
    {
        $searchValue = $this->readGetString('s', $errors);
        $search = '';
        if ($searchValue !== null) {
            $search = trim(sanitize_text_field($searchValue));
            $searchLength = $this->displayLength($search);

            if ($this->displayLength($searchValue) > self::MAX_SEARCH_LENGTH) {
                $errors[] = 'Search terms must contain no more than 100 characters.';
            } elseif ($search !== '' && $searchLength < 2) {
                $errors[] = 'Search terms must contain at least two characters.';
            } elseif ($searchLength > self::MAX_SEARCH_LENGTH) {
                $errors[] = 'Search terms must contain no more than 100 characters.';
            }
        }

        $sourceIdValue = $this->readGetString('source_id', $errors);
        $sourceId = null;
        if ($sourceIdValue !== null && $sourceIdValue !== '') {
            $sourceId = $this->parsePositiveInteger($sourceIdValue);
            if ($sourceId === null) {
                $errors[] = 'The source filter is invalid.';
            }
        }

        $statusValue = $this->readGetString('status', $errors);
        $status = null;
        if ($statusValue !== null && $statusValue !== '') {
            if (!in_array($statusValue, array('new', 'updated'), true)) {
                $errors[] = 'The status filter is invalid.';
            } else {
                $status = $statusValue;
            }
        }

        $dateFromValue = $this->readGetString('date_from', $errors);
        $dateToValue = $this->readGetString('date_to', $errors);
        $dateFrom = $this->parseDate($dateFromValue);
        $dateTo = $this->parseDate($dateToValue);

        if ($dateFromValue !== null && $dateFromValue !== '' && $dateFrom === null) {
            $errors[] = 'The start date is invalid.';
        }

        if ($dateToValue !== null && $dateToValue !== '' && $dateTo === null) {
            $errors[] = 'The end date is invalid.';
        }

        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            $errors[] = 'The start date must not be later than the end date.';
        }

        $sortValue = $this->readGetString('sort', $errors);
        $allowedSorts = array('published', 'created', 'title', 'id', 'last_seen', 'updated');
        $sort = $sortValue === null ? 'updated' : $sortValue;
        if (!in_array($sort, $allowedSorts, true)) {
            $errors[] = 'The sort selection is invalid.';
            $sort = 'updated';
        }

        $directionValue = $this->readGetString('direction', $errors);
        $direction = $directionValue === null ? 'desc' : $directionValue;
        if (!in_array($direction, array('asc', 'desc'), true)) {
            $errors[] = 'The sort direction is invalid.';
            $direction = 'desc';
        }

        $pageValue = $this->readGetString('paged', $errors);
        $page = 1;
        if ($pageValue !== null) {
            $parsedPage = $this->parsePositiveInteger($pageValue);
            if ($parsedPage === null || $parsedPage > self::MAX_PAGE) {
                $errors[] = 'The requested page is invalid.';
            } else {
                $page = $parsedPage;
            }
        }

        return array(
            'search' => $search,
            'source_id' => $sourceId,
            'status' => $status,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'sort' => $sort,
            'direction' => $direction,
            'page' => $page,
        );
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

        $maximum = (string) PHP_INT_MAX;
        if (
            strlen($normalized) > strlen($maximum)
            || (
                strlen($normalized) === strlen($maximum)
                && strcmp($normalized, $maximum) > 0
            )
        ) {
            return null;
        }

        return (int) $normalized;
    }

    private function parseDate($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            return null;
        }

        $parts = explode('-', $value);
        if (!checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            return null;
        }

        return $value;
    }

    private function loadSourceOptions(array &$data)
    {
        $result = $this->repository->findSourceOptions();
        if ($result['ok'] !== true) {
            $data['error_messages'][] = 'Source filter options could not be loaded. Please try again.';
            return;
        }

        foreach ($result['sources'] as $source) {
            if (!is_array($source)) {
                continue;
            }

            $data['source_options'][] = array(
                'id' => isset($source['id']) ? (int) $source['id'] : 0,
                'name' => $this->displayValue(isset($source['name']) ? $source['name'] : null),
                'slug' => $this->displayValue(isset($source['slug']) ? $source['slug'] : null),
            );
        }

        $data['source_options_truncated'] = $result['truncated'] === true;
    }

    private function buildListItem(array $item)
    {
        $revisionNo = isset($item['revision_no']) ? (int) $item['revision_no'] : 0;

        return array(
            'id' => isset($item['id']) ? (int) $item['id'] : 0,
            'source_id' => isset($item['source_id']) ? (int) $item['source_id'] : 0,
            'source_name' => $this->displayValue(isset($item['source_name']) ? $item['source_name'] : null),
            'title' => $this->displayValue(isset($item['raw_title']) ? $item['raw_title'] : null),
            'status' => $this->queryService->statusFromRevision($revisionNo),
            'revision_no' => $this->displayValue(isset($item['revision_no']) ? $item['revision_no'] : null),
            'first_seen_at_utc' => $this->displayValue(
                isset($item['first_seen_at_utc']) ? $item['first_seen_at_utc'] : null
            ),
            'last_seen_at_utc' => $this->displayValue(
                isset($item['last_seen_at_utc']) ? $item['last_seen_at_utc'] : null
            ),
            'updated_at_utc' => $this->displayValue(
                isset($item['updated_at_utc']) ? $item['updated_at_utc'] : null
            ),
        );
    }

    private function buildDetailItem(array $item)
    {
        $rawPayload = isset($item['display_raw_payload']) && is_scalar($item['display_raw_payload'])
            ? (string) $item['display_raw_payload']
            : '';
        $rawPayloadBytes = isset($item['raw_payload_bytes'])
            ? max(0, (int) $item['raw_payload_bytes'])
            : 0;
        $decodedPayload = json_decode($rawPayload, true);
        $jsonIsValid = json_last_error() === JSON_ERROR_NONE;
        $prettyPayload = '';
        $revisionNo = isset($item['revision_no']) ? (int) $item['revision_no'] : 0;

        if ($jsonIsValid) {
            $encodedPayload = wp_json_encode(
                $decodedPayload,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
            );
            $prettyPayload = is_string($encodedPayload) ? $encodedPayload : '';
        }

        return array(
            'id' => $this->displayValue(isset($item['id']) ? $item['id'] : null),
            'source_id' => $this->displayValue(isset($item['source_id']) ? $item['source_id'] : null),
            'source_name' => $this->displayValue(isset($item['source_name']) ? $item['source_name'] : null),
            'source_slug' => $this->displayValue(isset($item['source_slug']) ? $item['source_slug'] : null),
            'identity_hash' => $this->displayValue(isset($item['identity_hash']) ? $item['identity_hash'] : null),
            'content_hash' => $this->displayValue(isset($item['content_hash']) ? $item['content_hash'] : null),
            'raw_title' => $this->displayValue(isset($item['raw_title']) ? $item['raw_title'] : null),
            'status' => $this->queryService->statusFromRevision($revisionNo),
            'revision_no' => $this->displayValue(isset($item['revision_no']) ? $item['revision_no'] : null),
            'first_seen_at_utc' => $this->displayValue(
                isset($item['first_seen_at_utc']) ? $item['first_seen_at_utc'] : null
            ),
            'last_seen_at_utc' => $this->displayValue(
                isset($item['last_seen_at_utc']) ? $item['last_seen_at_utc'] : null
            ),
            'updated_at_utc' => $this->displayValue(
                isset($item['updated_at_utc']) ? $item['updated_at_utc'] : null
            ),
            'raw_payload_bytes' => $rawPayloadBytes,
            'payload_truncated' => strlen($rawPayload) < $rawPayloadBytes,
            'json_is_valid' => $jsonIsValid,
            'pretty_payload' => $prettyPayload,
            'raw_payload' => $rawPayload,
        );
    }

    private function displayValue($value)
    {
        $display = $this->stringValue($value);
        return $display === '' ? '—' : $display;
    }

    private function stringValue($value)
    {
        if (!is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    private function displayLength($value)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }

    private function buildFilterDisplay(array $criteria)
    {
        return array(
            'search' => $criteria['search'],
            'source_id' => $criteria['source_id'] === null ? '' : (string) $criteria['source_id'],
            'status' => $criteria['status'] === null ? '' : (string) $criteria['status'],
            'date_from' => $criteria['date_from'] === null ? '' : $criteria['date_from'],
            'date_to' => $criteria['date_to'] === null ? '' : $criteria['date_to'],
            'sort' => $criteria['sort'],
            'direction' => $criteria['direction'],
        );
    }

    private function buildListQueryArgs(array $criteria, $includePage)
    {
        $args = array();

        if ($criteria['search'] !== '') {
            $args['s'] = $criteria['search'];
        }
        if ($criteria['source_id'] !== null) {
            $args['source_id'] = $criteria['source_id'];
        }
        if ($criteria['status'] !== null) {
            $args['status'] = $criteria['status'];
        }
        if ($criteria['date_from'] !== null) {
            $args['date_from'] = $criteria['date_from'];
        }
        if ($criteria['date_to'] !== null) {
            $args['date_to'] = $criteria['date_to'];
        }
        if ($criteria['sort'] !== 'updated') {
            $args['sort'] = $criteria['sort'];
        }
        if ($criteria['direction'] !== 'desc') {
            $args['direction'] = $criteria['direction'];
        }
        if ($includePage && $criteria['page'] > 1) {
            $args['paged'] = $criteria['page'];
        }

        return $args;
    }

    private function buildAdminUrl(array $queryArgs)
    {
        $args = array_merge(array('page' => 'smce-editorial-announcements'), $queryArgs);
        return add_query_arg($args, admin_url('admin.php'));
    }

    private function emptyViewData()
    {
        return array(
            'title' => 'Announcements',
            'mode' => 'list',
            'error_messages' => array(),
            'success_messages' => array(),
            'filters' => array(
                'search' => '',
                'source_id' => '',
                'status' => '',
                'date_from' => '',
                'date_to' => '',
                'sort' => 'updated',
                'direction' => 'desc',
            ),
            'form_url' => admin_url('admin.php'),
            'reset_url' => $this->buildAdminUrl(array()),
            'back_url' => $this->buildAdminUrl(array()),
            'workspace_url' => add_query_arg(array('page' => 'smce-editorial'), admin_url('admin.php')),
            'source_options' => array(),
            'source_options_truncated' => false,
            'items' => array(),
            'detail_item' => null,
            'lifecycle_diagnostics' => null,
            'spine_ready' => 'Not ready',
            'generate_form_url' => '',
            'generate_nonce_action' => '',
            'article_preview' => null,
            'generation_result' => null,
            'last_generation' => null,
            'page' => 1,
            'has_previous' => false,
            'has_next' => false,
            'limit_reached' => false,
            'previous_url' => '',
            'next_url' => '',
        );
    }
}
