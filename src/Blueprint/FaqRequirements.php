<?php

namespace StudyMentor\ContentEngine\Blueprint;

defined('ABSPATH') || exit;

/**
 * FAQ requirement block.
 */
final class FaqRequirements
{
    private $enabled;
    private $minItems;
    private $maxItems;
    /** @var array<int, string> */
    private $topics;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->enabled = isset($data['enabled']) && $data['enabled'] === true;
        $this->minItems = isset($data['min_items']) ? (int) $data['min_items'] : 0;
        $this->maxItems = isset($data['max_items']) ? (int) $data['max_items'] : 0;
        $this->topics = array();

        if (isset($data['topics']) && is_array($data['topics'])) {
            foreach ($data['topics'] as $topic) {
                if (is_scalar($topic) && (string) $topic !== '') {
                    $this->topics[] = (string) $topic;
                }
            }
        }
    }

    /** @return bool */
    public function enabled()
    {
        return $this->enabled;
    }

    /** @return int */
    public function minItems()
    {
        return $this->minItems;
    }

    /** @return int */
    public function maxItems()
    {
        return $this->maxItems;
    }

    /**
     * @return array<int, string>
     */
    public function topics()
    {
        return $this->topics;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return array(
            'enabled' => $this->enabled,
            'min_items' => $this->minItems,
            'max_items' => $this->maxItems,
            'topics' => $this->topics,
        );
    }
}
