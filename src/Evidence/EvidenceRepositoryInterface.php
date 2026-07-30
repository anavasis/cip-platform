<?php

namespace StudyMentor\ContentEngine\Evidence;

defined('ABSPATH') || exit;

interface EvidenceRepositoryInterface
{
    /**
     * @return string Stored evidence key
     */
    public function store(Evidence $evidence);

    /**
     * @param string $key
     * @return Evidence|null
     */
    public function find($key);

    /**
     * @return int
     */
    public function count();

    /**
     * @return array<string, Evidence>
     */
    public function all();
}
