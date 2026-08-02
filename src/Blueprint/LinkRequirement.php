<?php

namespace StudyMentor\ContentEngine\Blueprint;

defined('ABSPATH') || exit;

/**
 * Internal or external link requirement.
 */
final class LinkRequirement
{
    public const KIND_INTERNAL = 'internal';
    public const KIND_EXTERNAL = 'external';

    private $kind;
    private $requirementKey;
    private $required;
    private $minCount;
    private $targetHint;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->kind = isset($data['kind']) ? (string) $data['kind'] : self::KIND_INTERNAL;
        $this->requirementKey = isset($data['requirement_key'])
            ? (string) $data['requirement_key']
            : '';
        $this->required = !isset($data['required']) || $data['required'] === true;
        $this->minCount = isset($data['min_count']) ? (int) $data['min_count'] : 0;
        $this->targetHint = isset($data['target_hint']) ? (string) $data['target_hint'] : '';
    }

    /** @return string */
    public function kind()
    {
        return $this->kind;
    }

    /** @return string */
    public function requirementKey()
    {
        return $this->requirementKey;
    }

    /** @return bool */
    public function required()
    {
        return $this->required;
    }

    /** @return int */
    public function minCount()
    {
        return $this->minCount;
    }

    /** @return string */
    public function targetHint()
    {
        return $this->targetHint;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'kind' => $this->kind,
            'requirement_key' => $this->requirementKey,
            'required' => $this->required,
            'min_count' => $this->minCount,
            'target_hint' => $this->targetHint,
        );
    }
}
