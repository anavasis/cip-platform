<?php

namespace StudyMentor\ContentEngine\Data;

defined('ABSPATH') || exit;

final class SourceItemReadRepository
{
    private const PAGE_SIZE = 25;
    private const QUERY_LIMIT = 26;
    private const MAX_PAGE = 200;
    private const SOURCE_OPTIONS_LIMIT = 201;
    private const MAX_SOURCE_OPTIONS = 200;

    private $wpdb;
    private $sourceItemsTableName;
    private $sourcesTableName;

    public function __construct($wpdbObject)
    {
        $this->wpdb = $wpdbObject;
        $this->sourceItemsTableName = $this->wpdb->prefix . 'smce_source_items';
        $this->sourcesTableName = $this->wpdb->prefix . 'smce_sources';
    }

    public function findPage(array $criteria): array
    {
        $page = isset($criteria['page']) && is_int($criteria['page'])
            ? $criteria['page']
            : 1;
        if ($page < 1 || $page > self::MAX_PAGE) {
            return $this->pageFailureResult(1);
        }

        $search = isset($criteria['search']) ? $criteria['search'] : '';
        if (
            !is_string($search)
            || ($search !== '' && $this->textLength($search) < 2)
            || $this->textLength($search) > 100
        ) {
            return $this->pageFailureResult($page);
        }

        $sourceId = array_key_exists('source_id', $criteria)
            ? $criteria['source_id']
            : null;
        if ($sourceId !== null && (!is_int($sourceId) || $sourceId <= 0)) {
            return $this->pageFailureResult($page);
        }

        $rawDateFrom = array_key_exists('date_from', $criteria)
            ? $criteria['date_from']
            : null;
        $rawDateTo = array_key_exists('date_to', $criteria)
            ? $criteria['date_to']
            : null;
        $dateFrom = $this->normalizeDateCriterion(
            $rawDateFrom
        );
        $dateTo = $this->normalizeDateCriterion(
            $rawDateTo
        );
        if (
            ($rawDateFrom !== null && $dateFrom === null)
            || ($rawDateTo !== null && $dateTo === null)
            || ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo)
        ) {
            return $this->pageFailureResult($page);
        }

        $sortExpressions = array(
            'published' => 'i.source_published_at_utc',
            'created' => 'i.created_at_utc',
            'title' => 'i.raw_title',
            'id' => 'i.id',
        );
        $sort = isset($criteria['sort']) ? $criteria['sort'] : 'published';
        if (!is_string($sort) || !isset($sortExpressions[$sort])) {
            return $this->pageFailureResult($page);
        }

        $direction = isset($criteria['direction']) ? $criteria['direction'] : 'desc';
        if (!is_string($direction) || !in_array($direction, array('asc', 'desc'), true)) {
            return $this->pageFailureResult($page);
        }

        $conditions = array('1 = 1');
        $values = array();

        if ($search !== '') {
            $likeValue = '%' . $this->wpdb->esc_like($search) . '%';
            $conditions[] = '(i.raw_title LIKE %s OR i.canonical_url LIKE %s)';
            $values[] = $likeValue;
            $values[] = $likeValue;
        }

        if ($sourceId !== null) {
            $conditions[] = 'i.source_id = %d';
            $values[] = $sourceId;
        }

        if ($dateFrom !== null) {
            $conditions[] = 'i.source_published_at_utc >= %s';
            $values[] = $dateFrom . ' 00:00:00';
        }

        if ($dateTo !== null) {
            $conditions[] = 'i.source_published_at_utc <= %s';
            $values[] = $dateTo . ' 23:59:59';
        }

        $offset = ($page - 1) * self::PAGE_SIZE;
        $values[] = self::QUERY_LIMIT;
        $values[] = $offset;

        $sql = 'SELECT '
            . 'i.id, '
            . 'i.source_id, '
            . 'i.identity_basis, '
            . 'i.canonical_url, '
            . 'i.source_published_at_utc, '
            . 'i.raw_title, '
            . 'LEFT(i.raw_payload, 131072) AS raw_payload, '
            . 'OCTET_LENGTH(i.raw_payload) AS raw_payload_bytes, '
            . 'i.revision_no, '
            . 'i.first_seen_at_utc, '
            . 'i.created_at_utc, '
            . 's.name AS source_name, '
            . 's.slug AS source_slug '
            . 'FROM ' . $this->sourceItemsTableName . ' i '
            . 'LEFT JOIN ' . $this->sourcesTableName . ' s ON s.id = i.source_id '
            . 'WHERE ' . implode(' AND ', $conditions) . ' '
            . 'ORDER BY ' . $sortExpressions[$sort] . ' ' . strtoupper($direction)
            . ', i.id ' . strtoupper($direction) . ' '
            . 'LIMIT %d OFFSET %d';

        $preparedSql = $this->wpdb->prepare($sql, $values);
        if (!is_string($preparedSql)) {
            return $this->pageFailureResult($page);
        }

        $rows = $this->fetchRowsOrNull($preparedSql);
        if ($rows === null) {
            return $this->pageFailureResult($page);
        }

        $hasExtraRow = count($rows) > self::PAGE_SIZE;
        $items = array_slice($rows, 0, self::PAGE_SIZE);
        $limitReached = $page === self::MAX_PAGE && $hasExtraRow;

        return array(
            'ok' => true,
            'items' => $items,
            'page' => $page,
            'page_size' => self::PAGE_SIZE,
            'has_previous' => $page > 1,
            'has_next' => $hasExtraRow && $page < self::MAX_PAGE,
            'limit_reached' => $limitReached,
        );
    }

    public function findById(int $itemId): array
    {
        if ($itemId <= 0) {
            return array(
                'ok' => true,
                'found' => false,
                'item' => null,
            );
        }

        $sql = 'SELECT '
            . 'i.id, '
            . 'i.source_id, '
            . 'i.identity_hash, '
            . 'i.identity_basis, '
            . 'i.source_guid, '
            . 'i.canonical_url, '
            . 'i.source_published_at_utc, '
            . 'i.raw_title, '
            . 'i.content_hash, '
            . 'LEFT(i.raw_payload, 262144) AS display_raw_payload, '
            . 'OCTET_LENGTH(i.raw_payload) AS raw_payload_bytes, '
            . 'i.revision_no, '
            . 'i.first_seen_at_utc, '
            . 'i.last_seen_at_utc, '
            . 'i.created_at_utc, '
            . 'i.updated_at_utc, '
            . 's.name AS source_name, '
            . 's.slug AS source_slug '
            . 'FROM ' . $this->sourceItemsTableName . ' i '
            . 'LEFT JOIN ' . $this->sourcesTableName . ' s ON s.id = i.source_id '
            . 'WHERE i.id = %d '
            . 'LIMIT 1';

        $preparedSql = $this->wpdb->prepare($sql, $itemId);
        if (!is_string($preparedSql)) {
            return $this->itemFailureResult();
        }

        $rows = $this->fetchRowsOrNull($preparedSql);
        if ($rows === null) {
            return $this->itemFailureResult();
        }

        if ($rows === array()) {
            return array(
                'ok' => true,
                'found' => false,
                'item' => null,
            );
        }

        return array(
            'ok' => true,
            'found' => true,
            'item' => $rows[0],
        );
    }

    public function findSourceOptions(): array
    {
        $sql = 'SELECT id, name, slug FROM ' . $this->sourcesTableName
            . ' ORDER BY id ASC LIMIT %d';
        $preparedSql = $this->wpdb->prepare($sql, self::SOURCE_OPTIONS_LIMIT);

        if (!is_string($preparedSql)) {
            return $this->sourceOptionsFailureResult();
        }

        $rows = $this->fetchRowsOrNull($preparedSql);
        if ($rows === null) {
            return $this->sourceOptionsFailureResult();
        }

        $truncated = count($rows) > self::MAX_SOURCE_OPTIONS;

        return array(
            'ok' => true,
            'sources' => array_slice($rows, 0, self::MAX_SOURCE_OPTIONS),
            'truncated' => $truncated,
        );
    }

    private function fetchRowsOrNull($preparedSql)
    {
        $previousSuppression = $this->wpdb->suppress_errors(true);
        $rows = null;
        $hasDbError = false;

        try {
            $rows = $this->wpdb->get_results($preparedSql, ARRAY_A);
            $hasDbError = $this->wpdb->last_error !== '';
        } finally {
            $this->wpdb->suppress_errors($previousSuppression);
        }

        if (!is_array($rows) || $hasDbError) {
            return null;
        }

        return $rows;
    }

    private function normalizeDateCriterion($value)
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            return null;
        }

        $parts = explode('-', $value);
        if (!checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
            return null;
        }

        return $value;
    }

    private function textLength($value)
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }

    private function pageFailureResult($page)
    {
        return array(
            'ok' => false,
            'items' => array(),
            'page' => $page,
            'page_size' => self::PAGE_SIZE,
            'has_previous' => $page > 1,
            'has_next' => false,
            'limit_reached' => false,
        );
    }

    private function itemFailureResult()
    {
        return array(
            'ok' => false,
            'found' => false,
            'item' => null,
        );
    }

    private function sourceOptionsFailureResult()
    {
        return array(
            'ok' => false,
            'sources' => array(),
            'truncated' => false,
        );
    }
}
