<?php

namespace StudyMentor\ContentEngine\Blueprint;

defined('ABSPATH') || exit;

/**
 * Heading hierarchy node.
 */
final class HeadingNode
{
    private $level;
    private $headingKey;
    private $title;
    private $required;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->level = isset($data['level']) ? (int) $data['level'] : 2;
        $this->headingKey = isset($data['heading_key']) ? (string) $data['heading_key'] : '';
        $this->title = isset($data['title']) ? (string) $data['title'] : '';
        $this->required = !isset($data['required']) || $data['required'] === true;
    }

    /** @return int */
    public function level()
    {
        return $this->level;
    }

    /** @return string */
    public function headingKey()
    {
        return $this->headingKey;
    }

    /** @return string */
    public function title()
    {
        return $this->title;
    }

    /** @return bool */
    public function required()
    {
        return $this->required;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'level' => $this->level,
            'heading_key' => $this->headingKey,
            'title' => $this->title,
            'required' => $this->required,
        );
    }
}
