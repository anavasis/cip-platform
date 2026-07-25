<?php

namespace StudyMentor\ContentEngine\Admin\Pages;

use StudyMentor\ContentEngine\Core\FeatureFlags;
use StudyMentor\ContentEngine\Data\SourceItemReadRepository;

defined('ABSPATH') || exit;

final class ImportedItemsPage
{
    private const MAX_SEARCH_LENGTH = 100;
    private const MAX_PAGE = 200;
    private const MAX_CATEGORY_LENGTH = 150;

    private $featureFlags;
    private $repository;
    private $viewPath;

    public function __construct(
        FeatureFlags $featureFlags,
        SourceItemReadRepository $repository,
        $viewPath
    ) {
        $this->featureFlags = $featureFlags;
        $this->repository = $repository;
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
        $criteria = $this->parseListCriteria($validationErrors);
        $hasItemId = array_key_exists('item_id', $_GET);
        $itemId = null;

        if ($hasItemId) {
            $itemIdValue = $this->readGetString('item_id', $validationErrors);
            $itemId = $this->parsePositiveInteger($itemIdValue);

            if ($itemId === null) {
                $validationErrors[] = 'The item identifier is invalid.';
            }
        }

        $data['filters'] = $this->buildFilterDisplay($criteria);
        $data['reset_url'] = $this->buildAdminUrl(array());
        $data['back_url'] = $this->buildAdminUrl(
            $this->buildListQueryArgs($criteria, true)
        );

        if ($validationErrors !== array()) {
            $data['error_messages'] = array_values(array_unique($validationErrors));
            $this->loadSourceOptions($data);
            require $this->viewPath;
            return;
        }

        if ($hasItemId) {
            $data['mode'] = 'detail';
            $result = $this->repository->findById($itemId);

            if ($result['ok'] !== true) {
                $data['error_messages'][] = 'The imported item could not be loaded. Please try again.';
            } elseif ($result['found'] !== true || !is_array($result['item'])) {
                $data['error_messages'][] = 'The requested imported item was not found.';
            } else {
                $data['detail_item'] = $this->buildDetailItem($result['item']);
            }

            require $this->viewPath;
            return;
        }

        $this->loadSourceOptions($data);
        $result = $this->repository->findPage($criteria);

        if ($result['ok'] !== true) {
            $data['error_messages'][] = 'Imported items could not be loaded. Please try again.';
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
        $allowedSorts = array('published', 'created', 'title', 'id');
        $sort = $sortValue === null ? 'published' : $sortValue;
        if (!in_array($sort, $allowedSorts, true)) {
            $errors[] = 'The sort selection is invalid.';
            $sort = 'published';
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
        return array(
            'id' => isset($item['id']) ? (int) $item['id'] : 0,
            'source_id' => isset($item['source_id']) ? (int) $item['source_id'] : 0,
            'source_name' => $this->displayValue(isset($item['source_name']) ? $item['source_name'] : null),
            'source_slug' => $this->displayValue(isset($item['source_slug']) ? $item['source_slug'] : null),
            'publication_date' => $this->displayValue(
                isset($item['source_published_at_utc']) ? $item['source_published_at_utc'] : null
            ),
            'category' => $this->extractCategory(
                isset($item['raw_payload']) ? $item['raw_payload'] : null
            ),
            'title' => $this->displayValue(isset($item['raw_title']) ? $item['raw_title'] : null),
            'canonical_url' => $this->stringValue(
                isset($item['canonical_url']) ? $item['canonical_url'] : null
            ),
            'canonical_url_display' => $this->displayValue(
                isset($item['canonical_url']) ? $item['canonical_url'] : null
            ),
            'identity_basis' => $this->displayValue(
                isset($item['identity_basis']) ? $item['identity_basis'] : null
            ),
            'revision_no' => $this->displayValue(
                isset($item['revision_no']) ? $item['revision_no'] : null
            ),
            'first_seen_at_utc' => $this->displayValue(
                isset($item['first_seen_at_utc']) ? $item['first_seen_at_utc'] : null
            ),
            'created_at_utc' => $this->displayValue(
                isset($item['created_at_utc']) ? $item['created_at_utc'] : null
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
            'identity_basis' => $this->displayValue(isset($item['identity_basis']) ? $item['identity_basis'] : null),
            'source_guid' => $this->displayValue(isset($item['source_guid']) ? $item['source_guid'] : null),
            'canonical_url' => $this->stringValue(isset($item['canonical_url']) ? $item['canonical_url'] : null),
            'canonical_url_display' => $this->displayValue(
                isset($item['canonical_url']) ? $item['canonical_url'] : null
            ),
            'source_published_at_utc' => $this->displayValue(
                isset($item['source_published_at_utc']) ? $item['source_published_at_utc'] : null
            ),
            'raw_title' => $this->displayValue(isset($item['raw_title']) ? $item['raw_title'] : null),
            'content_hash' => $this->displayValue(isset($item['content_hash']) ? $item['content_hash'] : null),
            'revision_no' => $this->displayValue(isset($item['revision_no']) ? $item['revision_no'] : null),
            'first_seen_at_utc' => $this->displayValue(
                isset($item['first_seen_at_utc']) ? $item['first_seen_at_utc'] : null
            ),
            'last_seen_at_utc' => $this->displayValue(
                isset($item['last_seen_at_utc']) ? $item['last_seen_at_utc'] : null
            ),
            'created_at_utc' => $this->displayValue(
                isset($item['created_at_utc']) ? $item['created_at_utc'] : null
            ),
            'updated_at_utc' => $this->displayValue(
                isset($item['updated_at_utc']) ? $item['updated_at_utc'] : null
            ),
            'category' => $this->extractCategory($rawPayload),
            'raw_payload_bytes' => $rawPayloadBytes,
            'payload_truncated' => strlen($rawPayload) < $rawPayloadBytes,
            'json_is_valid' => $jsonIsValid,
            'pretty_payload' => $prettyPayload,
            'raw_payload' => $rawPayload,
        );
    }

    private function extractCategory($rawPayload)
    {
        if (!is_string($rawPayload) || $rawPayload === '') {
            return '—';
        }

        $decoded = json_decode($rawPayload, true);
        if (
            json_last_error() !== JSON_ERROR_NONE
            || !is_array($decoded)
            || !array_key_exists('category', $decoded)
            || !is_string($decoded['category'])
        ) {
            return '—';
        }

        $category = trim($decoded['category']);
        if (
            $category === ''
            || $this->displayLength($category) > self::MAX_CATEGORY_LENGTH
        ) {
            return '—';
        }

        return $category;
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
        if ($criteria['date_from'] !== null) {
            $args['date_from'] = $criteria['date_from'];
        }
        if ($criteria['date_to'] !== null) {
            $args['date_to'] = $criteria['date_to'];
        }
        if ($criteria['sort'] !== 'published') {
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
        $args = array_merge(array('page' => 'smce-imported-items'), $queryArgs);
        return add_query_arg($args, admin_url('admin.php'));
    }

    private function emptyViewData()
    {
        return array(
            'title' => 'Imported Items',
            'mode' => 'list',
            'error_messages' => array(),
            'filters' => array(
                'search' => '',
                'source_id' => '',
                'date_from' => '',
                'date_to' => '',
                'sort' => 'published',
                'direction' => 'desc',
            ),
            'form_url' => admin_url('admin.php'),
            'reset_url' => $this->buildAdminUrl(array()),
            'back_url' => $this->buildAdminUrl(array()),
            'source_options' => array(),
            'source_options_truncated' => false,
            'items' => array(),
            'detail_item' => null,
            'page' => 1,
            'has_previous' => false,
            'has_next' => false,
            'limit_reached' => false,
            'previous_url' => '',
            'next_url' => '',
        );
    }
}
