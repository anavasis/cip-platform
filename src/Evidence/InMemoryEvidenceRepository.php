<?php

namespace StudyMentor\ContentEngine\Evidence;

defined('ABSPATH') || exit;

/**
 * Request-scoped evidence store. No database schema and no disk writes.
 */
final class InMemoryEvidenceRepository implements EvidenceRepositoryInterface
{
    /** @var array<string, Evidence> */
    private $items = array();

    /** @var int */
    private $storeOperations = 0;

    /**
     * @return string
     */
    public function store(Evidence $evidence)
    {
        $key = $this->resolveStorageKey($evidence);
        $this->items[$key] = $evidence;
        $this->storeOperations++;

        return $key;
    }

    /**
     * @param string $key
     * @return Evidence|null
     */
    public function find($key)
    {
        $normalized = (string) $key;

        return isset($this->items[$normalized]) ? $this->items[$normalized] : null;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->items);
    }

    /**
     * @return array<string, Evidence>
     */
    public function all()
    {
        return $this->items;
    }

    /**
     * @return int
     */
    public function storeOperations()
    {
        return $this->storeOperations;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function summaries()
    {
        $summaries = array();

        foreach ($this->items as $key => $evidence) {
            $summary = $evidence->toMetadataArray();
            $summary['storage_key'] = $key;
            $summaries[] = $summary;
        }

        return $summaries;
    }

    /**
     * @return string
     */
    private function resolveStorageKey(Evidence $evidence)
    {
        $key = $evidence->contentHash();

        if ($key === '') {
            $key = $evidence->bodyHash();
        }

        if ($key === '') {
            $key = hash('sha256', $evidence->url() . '|' . $evidence->fetchedAt());
        }

        return $key;
    }
}
