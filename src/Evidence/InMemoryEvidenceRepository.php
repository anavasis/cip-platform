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

    /**
     * @return string
     */
    public function store(Evidence $evidence)
    {
        $key = $evidence->contentHash();

        if ($key === '') {
            $key = $evidence->bodyHash();
        }

        if ($key === '') {
            $key = hash('sha256', $evidence->url() . '|' . $evidence->fetchedAt());
        }

        $this->items[$key] = $evidence;

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
}
